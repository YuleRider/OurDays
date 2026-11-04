(() => {
  'use strict';
  if (window.likegirlCoupleLoaded) return;
  window.likegirlCoupleLoaded = true;
  // Keep the original header and quote; desktop navigation follows its banner.
  if (document.body.classList.contains('couple-legacy')) {
    const banner = document.querySelector('.bg-wrap');
    const navigation = document.querySelector('.couple-navigation');
    if (banner && navigation) banner.after(navigation);
  }
  const $ = selector => document.querySelector(selector);
  const state = { user: null, csrf: '', timer: null };
  let toastTimer;
  function toast(message) {
    let node = $('#couple-toast');
    if (!node) { node = document.createElement('div'); node.id = 'couple-toast'; node.setAttribute('role', 'status'); document.body.append(node); }
    node.textContent = message; node.classList.add('is-visible');
    clearTimeout(toastTimer); toastTimer = setTimeout(() => node.classList.remove('is-visible'), 4500);
  }
  async function api(action, data, method = 'POST', keepalive = false) {
    const controller = new AbortController();
    const deadline = setTimeout(() => controller.abort(), 20000);
    const options = { credentials: 'same-origin', cache: 'no-store', headers: {}, signal: controller.signal, keepalive };
    let url = '/couple-api.php';
    if (method === 'GET') url += '?' + new URLSearchParams({ action, ...data });
    else { options.method = 'POST'; options.headers = { 'Content-Type': 'application/json', 'X-CSRF-Token': state.csrf }; options.body = JSON.stringify({ action, ...data }); }
    try {
      const response = await fetch(url, options);
      const result = await response.json();
      if (!response.ok || !result.ok) { const error = new Error(result.message || '暂时无法完成，请稍后重试'); error.status = response.status; throw error; }
      return result.data;
    } catch (error) { if (error.name === 'AbortError') throw new Error('请求超时，输入已保留，请重试'); throw error; }
    finally { clearTimeout(deadline); }
  }
  const ready = api('bootstrap', {}, 'GET').then(data => { if (document.body.hasAttribute('data-couple-user') && document.body.dataset.coupleUser !== String(data.user?.id || '')) { location.reload(); throw new Error('登录状态已变化，正在刷新'); } state.user = data.user; state.csrf = data.csrf; badges(data.unread); return data; }).catch(error => { toast(error.message || '网络暂不可用，请刷新重试'); throw error; });
  ready.catch(() => {});
  function badges(count) {
    document.querySelectorAll('.unread-dot').forEach(node => { node.hidden = count <= 0; node.textContent = count > 99 ? '99+' : String(count); });
  }
  function times(root = document) {
    root.querySelectorAll('time[datetime]').forEach(node => { const date = new Date(node.dateTime); if (!Number.isNaN(date.getTime())) node.textContent = new Intl.DateTimeFormat('zh-CN', { timeZone: 'Asia/Shanghai', month: 'long', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false }).format(date); });
  }
  function el(tag, className, text) { const node = document.createElement(tag); if (className) node.className = className; if (text != null) node.textContent = text; return node; }
  function link(url, label, className = 'text-link') { const node = el('a', className, label); node.href = url; node.dataset.noPjax = ''; return node; }
  function card(post) {
    const node = el('article', 'daily-card'); node.dataset.momentId = post.id;
    const header = el('header', 'daily-byline'); const avatar = el('img'); avatar.src = post.author.avatar; avatar.alt = ''; avatar.loading = 'lazy'; header.append(avatar);
    const author = el('div'); author.append(el('b', '', post.author.name)); author.append(el('span', 'moment-day', post.occurredOn.replaceAll('-', '.'))); if (post.backfilled) author.append(el('span', 'backfill-tag', '补记')); header.append(author);
    if (state.user) header.append(el('span', 'visibility-tag', post.visibility === 'private' ? '仅我们可见' : '公开')); node.append(header);
    if (post.body) node.append(el('p', 'daily-body', post.body));
    if (post.images.length && !post.deleted) {
      const grid = el('div', 'daily-grid count-' + post.images.length);
      post.images.forEach(photo => { const button = el('button', 'image-button'); button.type = 'button'; button.dataset.display = photo.url; if (photo.original) button.dataset.original = photo.original; button.setAttribute('aria-label', '查看照片'); const image = el('img'); image.src = photo.url; image.alt = '日常照片'; image.loading = 'lazy'; image.width = Number(photo.width); image.height = Number(photo.height); button.append(image); grid.append(button); }); node.append(grid);
    }
    const actions = el('footer', 'daily-actions');
    if (post.deleted) { const restore = el('button', 'pill-button', '恢复这条记录'); restore.type = 'button'; restore.dataset.restore = post.id; actions.append(restore); }
    else {
      actions.append(link('/moments.php?id=' + post.id, '看看这一天 ↗'));
      if (state.user) {
        if (!post.mine) { const like = el('button', 'like-button' + (post.liked ? ' is-liked' : ''), '♡ 喜欢 ' + post.likes); like.type = 'button'; like.dataset.like = post.id; like.dataset.liked = post.liked ? '1' : '0'; like.setAttribute('aria-pressed', post.liked ? 'true' : 'false'); actions.append(like); }
        else actions.append(el('span', 'muted', '♡ ' + post.likes + ' 个喜欢'));
        actions.append(link('/moments.php?id=' + post.id + '#replies', '回应 ' + post.replyCount));
        if (post.mine) { actions.append(link('/moments.php?view=write&id=' + post.id, '编辑')); const remove = el('button', 'text-link danger', '移入回收站'); remove.type = 'button'; remove.dataset.delete = post.id; actions.append(remove); }
      }
    }
    node.append(actions); times(node); return node;
  }
  function authChanged() { if ('BroadcastChannel' in window) { const channel = new BroadcastChannel('likegirl-auth'); channel.postMessage('changed'); channel.close(); } }
  if ('BroadcastChannel' in window) { const channel = new BroadcastChannel('likegirl-auth'); channel.onmessage = () => location.reload(); }
  window.addEventListener('pageshow', event => { if (event.persisted) location.reload(); });
  function poll() {
    clearTimeout(state.timer);
    if (!state.user || document.hidden) return;
    state.timer = setTimeout(async () => { if (document.hidden) return; try { const result = await api('unread', {}, 'GET'); badges(result.count); } catch (error) { if (error.status === 401) return location.reload(); } poll(); }, 30000);
  }
  document.addEventListener('visibilitychange', async () => { if (document.hidden) clearTimeout(state.timer); else { try { const current = await api('bootstrap', {}, 'GET'); if ((current.user?.id || 0) !== (state.user?.id || 0)) return location.reload(); state.csrf = current.csrf; badges(current.unread); } catch (_) {} poll(); } });
  window.addEventListener('pagehide', () => clearTimeout(state.timer));
  times(); ready.then(poll).catch(() => {});
  const photoViewer = $('#photo-viewer');
  let photoGroup = [], photoIndex = 0, touchStart = null;
  function showPhoto(index) {
    if (!photoViewer || index < 0 || index >= photoGroup.length) return;
    photoIndex = index;
    const item = photoGroup[index], image = $('#viewer-image');
    image.removeAttribute('src'); $('#viewer-error').textContent = '';
    image.src = item.dataset.display;
    const original = $('#viewer-original'); original.hidden = !item.dataset.original;
    original.removeAttribute('href'); if (item.dataset.original) original.href = item.dataset.original;
    $('#viewer-counter').textContent = (index + 1) + ' / ' + photoGroup.length;
    $('#viewer-prev').disabled = index === 0; $('#viewer-next').disabled = index === photoGroup.length - 1;
  }
  if (photoViewer) {
    photoViewer.addEventListener('close', () => { $('#viewer-image').removeAttribute('src'); $('#viewer-original').removeAttribute('href'); photoGroup = []; });
    photoViewer.addEventListener('keydown', event => { if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') { event.preventDefault(); showPhoto(photoIndex + (event.key === 'ArrowLeft' ? -1 : 1)); } });
    $('#viewer-image').addEventListener('error', () => { $('#viewer-error').textContent = '照片暂时无法读取，可能已被删除或调整了可见范围。'; });
    $('#viewer-image').addEventListener('pointerdown', event => { touchStart = event.pointerType === 'touch' && event.isPrimary ? { x: event.clientX, y: event.clientY } : null; });
    $('#viewer-image').addEventListener('pointerup', event => { if (!touchStart) return; const dx = event.clientX - touchStart.x, dy = event.clientY - touchStart.y; touchStart = null; if (Math.abs(dx) > 50 && Math.abs(dy) < 80) showPhoto(photoIndex + (dx < 0 ? 1 : -1)); });
    $('#viewer-image').addEventListener('pointercancel', () => { touchStart = null; });
  }
  const calendar = document.querySelector('[data-calendar-today]');
  if (calendar) {
    const serverNow = Number(calendar.dataset.calendarNow), started = performance.now();
    const formatter = new Intl.DateTimeFormat('en', { timeZone: 'Asia/Shanghai', year: 'numeric', month: '2-digit', day: '2-digit' });
    const checkDate = () => {
      if (document.hidden) return;
      const parts = formatter.formatToParts(new Date(serverNow + performance.now() - started));
      const today = ['year', 'month', 'day'].map(type => parts.find(part => part.type === type).value).join('-');
      if (today !== calendar.dataset.calendarToday) location.reload();
    };
    const calendarTimer = setInterval(checkDate, 60000);
    document.addEventListener('visibilitychange', checkDate);
    window.addEventListener('pagehide', () => clearInterval(calendarTimer));
  }
  const together = document.querySelector('[data-together-since]');
  if (together && together.dataset.togetherSince) {
    const tick = () => { const seconds = Math.max(0, Math.floor((Date.now() - Number(together.dataset.togetherSince)) / 1000)); $('#together-days').textContent = Math.floor(seconds / 86400); $('#together-detail').textContent = Math.floor(seconds % 86400 / 3600) + ' 时 · ' + Math.floor(seconds % 3600 / 60) + ' 分 · ' + seconds % 60 + ' 秒，每一刻都想和你分享'; };
    tick(); const clock = setInterval(tick, 1000); window.addEventListener('pagehide', () => clearInterval(clock));
  }
  document.addEventListener('click', async event => {
    const button = event.target.closest('button'); if (!button) return;
    try {
      if (button.dataset.display) {
        if (!photoViewer) return;
        photoGroup = Array.from(button.closest('.daily-grid').querySelectorAll('[data-display]'));
        showPhoto(photoGroup.indexOf(button)); photoViewer.showModal(); return;
      }
      if (button.id === 'viewer-close') { $('#photo-viewer').close(); return; }
      if (button.id === 'viewer-prev' || button.id === 'viewer-next') { showPhoto(photoIndex + (button.id === 'viewer-prev' ? -1 : 1)); return; }
      if (button.dataset.like) {
        await ready; button.disabled = true; const liked = button.dataset.liked !== '1';
        await api('like', { id: button.dataset.like, liked });
        const post = await api('detail', { id: button.dataset.like }, 'GET');
        button.dataset.liked = post.liked ? '1' : '0'; button.classList.toggle('is-liked', post.liked); button.setAttribute('aria-pressed', String(post.liked)); button.textContent = '♡ 喜欢 ' + post.likes; button.disabled = false; return;
      }
      if (button.dataset.delete) {
        if (!confirm('将这条日常移入回收站？七天内可以恢复，照片和回应也会一起隐藏。')) return;
        await ready; button.disabled = true; await api('delete', { id: button.dataset.delete }); location.assign('/moments.php'); return;
      }
      if (button.dataset.restore) { await ready; button.disabled = true; await api('restore', { id: button.dataset.restore }); location.assign('/moments.php'); return; }
      if (button.dataset.dateDelete) {
        if (!confirm('删除这个日期？需要时可以重新添加。')) return;
        await ready; button.disabled = true; await api('deleteDate', { id: button.dataset.dateDelete }); location.assign('/moments.php?view=dates'); return;
      }
      if (button.dataset.taskComplete) {
        await ready; button.disabled = true;
        await api('completeTask', { key: button.dataset.taskComplete, revision: Number(button.dataset.revision), completed: button.dataset.completed !== '1' }); location.assign('/list.php'); return;
      }
      if (button.id === 'couple-logout') { await ready; button.disabled = true; await api('logout', {}); authChanged(); location.replace('/moments.php?view=me'); return; }
    } catch (error) { button.disabled = false; toast(error.message || '网络暂不可用，请稍后重试'); }
  });
  const feedMore = $('#feed-more');
  if (feedMore) feedMore.addEventListener('click', async () => { try { await ready; feedMore.disabled=true; const feed=$('#moment-feed'); const args={cursor:feedMore.dataset.next,trash:feed.dataset.trash||'',q:feed.dataset.query||'',scope:feed.dataset.scope||'all',photos:feed.dataset.photos||'',day:feed.dataset.day||''}; let action=feed.dataset.memories ? 'memories' : 'feed'; if(feed.dataset.story){action='storyFeed';args.id=feed.dataset.story;}else if(feed.dataset.month){action='monthFeed';args.month=feed.dataset.month;} const data=await api(action,args,'GET'); data.items.forEach(post=>{if(!feed.querySelector('[data-moment-id="'+post.id+'"]'))feed.append(card(post));}); feedMore.dataset.next=data.next||''; feedMore.hidden=!data.next; } catch(error){toast(error.message);} finally{feedMore.disabled=false;} });
  const login = $('#couple-login');
  if (login) login.addEventListener('submit', async event => { event.preventDefault(); const button = login.querySelector('button'); try { await ready; button.disabled = true; await api('login', { name: login.elements.name.value, password: login.elements.password.value }); login.elements.password.value = ''; authChanged(); location.replace('/moments.php?view=me'); } catch (error) { toast(error.message || '网络暂不可用，请重试'); button.disabled = false; } });
  const randomKey = () => Array.from(crypto.getRandomValues(new Uint8Array(16)), byte => byte.toString(16).padStart(2, '0')).join('');
  const storyEditor = $('#story-editor');
  if (storyEditor) {
    const requestKey = randomKey();
    storyEditor.addEventListener('submit', async event => {
      event.preventDefault(); const button=storyEditor.querySelector('button[type=submit]');
      try {
        await ready; button.disabled=true; $('#story-error').textContent='';
        const result=await api('saveStory',{id:storyEditor.dataset.id,title:storyEditor.elements.title.value,intro:storyEditor.elements.intro.value,revision:Number(storyEditor.dataset.revision),requestKey});
        const moment=$('#story-list')?.dataset.moment;
        location.assign(moment && moment!=='0' ? '/moments.php?view=stories&momentId='+moment : '/moments.php?view=story&storyId='+result.id);
      } catch(error){$('#story-error').textContent=error.message;button.disabled=false;}
    });
  }
  function storyTile(item,moment) {
    const tile=el('article','story-tile panel'),cover=link('/moments.php?view=story&storyId='+item.id,'','story-cover');
    if(item.cover){const image=el('img');image.src=item.cover;image.alt='故事里的照片';image.loading='lazy';cover.append(image);}else cover.append(el('span','','♡'));
    const content=el('div');content.append(el('small','story-number','故事 '+item.id+' · '+item.count+' 条日常'));const title=el('h2');title.append(link('/moments.php?view=story&storyId='+item.id,item.title,''));content.append(title,el('p','',item.intro));
    if(moment && moment!=='0'){const b=el('button','secondary-button',item.joined?'已加入 · 移出故事':'加入这个故事');b.type='button';b.dataset.storyJoin=item.id;b.dataset.moment=moment;b.dataset.joined=item.joined?'1':'0';b.setAttribute('aria-pressed',String(item.joined));content.append(b);}
    tile.append(cover,content);return tile;
  }
  document.addEventListener('click',async event=>{
    const b=event.target.closest('[data-story-join],#story-delete,#stories-more');if(!b)return;
    try {
      await ready;
      if(b.id==='story-delete'){
        if(!confirm('删除这个主题？其中的日常和照片都会保留。'))return;
        b.disabled=true;await api('deleteStory',{id:b.dataset.id,revision:Number(b.dataset.revision)});location.assign('/moments.php?view=stories');
      }else if(b.id==='stories-more'){
        b.disabled=true;const list=$('#story-list'),data=await api('stories',{before:b.dataset.next,momentId:list.dataset.moment},'GET');
        data.items.forEach(item=>list.append(storyTile(item,list.dataset.moment)));b.dataset.next=data.next||'';b.hidden=!data.next;
      }else{
        b.disabled=true;const joined=b.dataset.joined!=='1';await api('storyMembership',{id:b.dataset.storyJoin,momentId:b.dataset.moment,joined});b.dataset.joined=joined?'1':'0';b.setAttribute('aria-pressed',String(joined));b.textContent=joined?'已加入 · 移出故事':'加入这个故事';toast(joined?'已加入这个故事':'已移出，原日常仍然保留');
      }
    }catch(error){toast(error.message);}finally{b.disabled=false;}
  });
  const taskEditor = $('#task-editor');
  if (taskEditor) {
    const key = randomKey();
    const changeCompleted = () => { $('#task-done-field').hidden = !taskEditor.elements.completed.checked; taskEditor.elements.completedDate.disabled = !taskEditor.elements.completed.checked; };
    taskEditor.elements.completed.addEventListener('change', changeCompleted); changeCompleted();
    taskEditor.addEventListener('submit', async event => {
      event.preventDefault(); const button = taskEditor.querySelector('button[type=submit]');
      try {
        await ready; button.disabled = true; $('#task-error').textContent = '';
        await api('saveTask', { key: taskEditor.dataset.key, revision: Number(taskEditor.dataset.revision), title: taskEditor.elements.title.value, dueDate: taskEditor.elements.dueDate.value, completed: taskEditor.elements.completed.checked, completedDate: taskEditor.elements.completedDate.value, note: taskEditor.elements.note.value, momentId: taskEditor.elements.momentId.value, requestKey: key });
        location.assign('/list.php');
      } catch (error) { button.disabled = false; $('#task-error').textContent = error.message || '保存失败，当前输入已保留'; }
    });
  }
  const dateEditor = $('#date-editor');
  if (dateEditor) {
    const key = randomKey();
    const changeKind = () => {
      $('#date-error').textContent = '';
      const birthday = dateEditor.elements.kind.value === 'birthday';
      for (const [selector, active] of [['#birthday-fields', birthday], ['#anniversary-fields', !birthday]]) {
        const group = $(selector); group.hidden = !active;
        group.querySelectorAll('input').forEach(input => { input.disabled = !active; input.required = active; });
      }
    };
    dateEditor.elements.kind.addEventListener('change', changeKind); changeKind();
    dateEditor.addEventListener('submit', async event => {
      event.preventDefault(); const button = dateEditor.querySelector('button[type=submit]');
      try {
        await ready; button.disabled = true; $('#date-error').textContent = '';
        await api('saveDate', { id: dateEditor.dataset.editId, title: dateEditor.elements.title.value, kind: dateEditor.elements.kind.value, date: dateEditor.elements.date.value, month: dateEditor.elements.month.value, day: dateEditor.elements.day.value, visibility: dateEditor.elements.visibility.value, requestKey: key });
        location.assign('/moments.php?view=dates');
      } catch (error) { $('#date-error').textContent = error.message || '保存失败，输入已保留，请重试'; button.disabled = false; }
    });
  }
  const editor = $('#moment-editor');
  if (editor) {
    const restored = JSON.parse($('#draft-data').textContent);
    let requestKey = restored.publishKey || randomKey(), queueRunning = false;
    let draftRevision = restored.revision, draftTimer, draftSaving = null, draftConflict = !!restored.conflict, leaving = false;
    const uploads = restored.images.map(item => ({ ...item, file: null, status: 'done', progress: 100 }));
    const body = editor.elements.body;
    let inspiration='';
    document.querySelectorAll('[data-inspiration]').forEach(button=>button.addEventListener('click',()=>{
      inspiration=button.dataset.inspiration;$('#inspiration-questions').textContent=inspiration;$('#inspiration-preview').hidden=false;
      document.querySelectorAll('[data-inspiration]').forEach(option=>option.setAttribute('aria-pressed',String(option===button)));
    }));
    $('#inspiration-insert')?.addEventListener('click',()=>{
      const value=body.value+(body.value ? '\n\n' : '')+inspiration;
      if(Array.from(value).length>2000)return toast('加入后会超过 2000 字，请先精简当前文字');
      body.value=value;body.dispatchEvent(new Event('input',{bubbles:true}));body.focus();body.setSelectionRange(body.value.length,body.value.length);
    });
    const captureDraft = () => ({ context: editor.dataset.editId || 0, body: body.value.trim(), occurredOn: editor.elements.occurredOn.value, baseRevision: restored.baseRevision, visibility: editor.elements.visibility.value, images: uploads.filter(item => item.status === 'done').map(item => item.id) });
    let savedDraft = JSON.stringify(captureDraft());
    const isDirty = () => savedDraft !== JSON.stringify(captureDraft()) || uploads.some(item => item.status !== 'done');
    function scheduleDraft() { clearTimeout(draftTimer); if (!draftConflict && !leaving) draftTimer = setTimeout(() => flushDraft().catch(() => {}), 1200); }
    async function flushDraft() {
      clearTimeout(draftTimer);
      if (draftSaving) await draftSaving;
      if (draftConflict) throw new Error('另一页面已更新草稿，请先复制当前文字，再刷新查看最新内容');
      const data = captureDraft(), snapshot = JSON.stringify(data);
      if (snapshot === savedDraft && draftRevision > 0) return;
      draftSaving = (async () => {
        try {
          await ready; $('#draft-status').textContent = '正在保存私密草稿…';
          const result = await api('saveDraft', { ...data, revision: draftRevision, requestKey }, 'POST', true);
          draftRevision = result.revision; savedDraft = snapshot;
          $('#draft-status').textContent = '私密草稿已保存，可换设备继续写。';
        } catch (error) {
          if (error.status === 409) draftConflict = true;
          $('#draft-status').textContent = '草稿尚未保存：' + (error.message || '请检查网络后重试'); throw error;
        } finally { draftSaving = null; }
      })();
      await draftSaving;
      if (JSON.stringify(captureDraft()) !== savedDraft && !leaving) return flushDraft();
    }
    body.addEventListener('input', scheduleDraft);
    editor.elements.occurredOn.addEventListener('change', scheduleDraft);
    editor.querySelectorAll('input[name=visibility]').forEach(input => input.addEventListener('change', scheduleDraft));
    document.addEventListener('visibilitychange', () => { if (document.hidden && isDirty() && !leaving) flushDraft().catch(() => {}); });
    window.addEventListener('beforeunload', event => { if (!leaving && isDirty()) { event.preventDefault(); event.returnValue = ''; } });
    $('#draft-save').addEventListener('click', () => flushDraft().catch(error => toast(error.message)));
    $('#draft-discard').addEventListener('click', async () => {
      if (queueRunning || uploads.some(item => item.status === 'uploading')) return toast('请等照片上传完成后再清空草稿');
      if (!confirm('清空这份私密草稿？已发布的日常不受影响。')) return;
      try { if (!draftConflict) await flushDraft(); await api('discardDraft', { context: editor.dataset.editId || 0, revision: draftRevision, requestKey }); leaving = true; location.reload(); } catch (error) { toast(error.message); }
    });
    const count = () => { $('#text-count').textContent = Array.from(body.value).length; };
    body.addEventListener('input', count); count();
    function renderUploads() {
      const list = $('#upload-list'); if (!list) return;
      list.replaceChildren();
      uploads.forEach((item, index) => {
        const box = el('div', 'upload-item'); box.append(el('b', 'photo-order', String(index + 1))); const image = el('img'); image.src = item.preview; image.alt = item.file ? item.file.name : item.name; box.append(image);
        const progress = el('progress'); progress.max = 100; progress.value = item.progress; box.append(progress);
        box.append(el('small', '', item.status === 'done' ? (item.existing ? '原有照片，保存后应用修改' : '已上传，保存后应用修改') : item.status === 'error' ? item.error : item.status === 'uploading' ? '上传中 ' + item.progress + '%' : '等待上传'));
        if (item.status === 'error') { const retry = el('button', '', '重试'); retry.type = 'button'; retry.onclick = () => { item.status = 'queued'; processQueue(); }; box.append(retry); }
        const remove = el('button', '', '移除'); remove.type = 'button'; remove.disabled = item.status === 'uploading';
        remove.onclick = () => { uploads.splice(uploads.indexOf(item), 1); if (item.file) URL.revokeObjectURL(item.preview); renderUploads(); scheduleDraft(); };
        const actions = el('div', 'photo-edit-actions');
        for (const [offset,label] of [[-1,'前移'],[1,'后移']]) { const move=el('button','',label); move.type='button'; move.disabled=index+offset<0||index+offset>=uploads.length; move.setAttribute('aria-label','第'+(index+1)+'张照片'+label); move.onclick=()=>{ [uploads[index],uploads[index+offset]]=[uploads[index+offset],uploads[index]]; renderUploads(); scheduleDraft(); }; actions.append(move); }
        actions.append(remove); box.append(actions); list.append(box);
      });
    }
    function upload(item) {
      return new Promise((resolve, reject) => {
        const request = new XMLHttpRequest(); request.open('POST', '/couple-api.php'); request.timeout = 120000; request.setRequestHeader('X-CSRF-Token', state.csrf);
        request.upload.onprogress = event => { if (event.lengthComputable) { item.progress = Math.round(event.loaded / event.total * 100); renderUploads(); } };
        request.onload = () => { try { const result = JSON.parse(request.responseText); if (request.status >= 200 && request.status < 300 && result.ok) resolve(result.data); else reject(new Error(result.message || '上传失败，请重试')); } catch (_) { reject(new Error('上传未确认，请重试；不会重复保存同一张照片')); } };
        request.onerror = request.ontimeout = () => reject(new Error('网络中断，请点击重试'));
        const data = new FormData(); data.set('action', 'upload'); data.set('requestKey', item.key); data.set('photo', item.file); request.send(data);
      });
    }
    async function processQueue() {
      if (queueRunning) return; queueRunning = true;
      try {
        await ready;
        for (;;) {
          const item = uploads.find(entry => entry.status === 'queued');
          if (!item) break;
          item.status = 'uploading'; renderUploads();
          try { const result = await upload(item); item.id = result.id; item.progress = 100; item.status = 'done'; scheduleDraft(); }
          catch (error) { item.status = 'error'; item.error = error.message; }
          renderUploads();
        }
      } catch (error) { toast(error.message); } finally { queueRunning = false; }
    }
    renderUploads();
    const picker = $('#moment-photos');
    if (picker) picker.addEventListener('change', () => {
      for (const file of picker.files) {
        if (uploads.length >= 9) { toast('最多选择九张照片'); break; }
        if (!/\.(jpe?g|png|webp)$/i.test(file.name)) { toast('支持 JPEG、PNG、WebP；HEIC 请先转换格式'); continue; }
        if (file.size > 10 * 1024 * 1024 || file.size === 0) { toast('单张照片需要在 10MB 以内'); continue; }
        uploads.push({ file, key: randomKey(), preview: URL.createObjectURL(file), status: 'queued', progress: 0 });
      }
      picker.value = ''; renderUploads(); processQueue();
    });
    editor.addEventListener('submit', async event => {
      event.preventDefault(); const button = editor.querySelector('button[type=submit]'); const errorBox = $('#editor-error');
      try {
        await ready;
        if (Array.from(body.value.trim()).length > 2000) throw new Error('日常文字最多 2000 字');
        if (uploads.some(item => item.status !== 'done') || queueRunning) throw new Error('请等照片上传完成，或移除失败的照片');
        if (!body.value.trim() && !uploads.length) throw new Error('写点文字或选几张照片吧');
        button.disabled = true; errorBox.textContent = '';
        await flushDraft();
        const action = editor.dataset.editId ? 'edit' : 'publish';
        const result = await api(action, { id: editor.dataset.editId, body: body.value, occurredOn: editor.elements.occurredOn.value, baseRevision: restored.baseRevision, visibility: editor.elements.visibility.value, images: uploads.map(item => item.id), requestKey, draftRevision });
        leaving = true; clearTimeout(draftTimer); location.replace('/moments.php?id=' + result.id);
      } catch (error) { errorBox.textContent = error.message || '网络暂不可用，输入内容已保留，请重试'; button.disabled = false; }
    });
  }
  const reply = $('#reply-form');
  if (reply) { let key = randomKey(); reply.addEventListener('submit', async event => { event.preventDefault(); const button = reply.querySelector('button'); try { await ready; if (Array.from(reply.elements.body.value.trim()).length > 500) throw new Error('回应最多 500 字'); button.disabled = true; await api('reply', { id: reply.dataset.id, body: reply.elements.body.value, requestKey: key }); key = randomKey(); location.reload(); } catch (error) { button.disabled = false; toast(error.message || '网络暂不可用，回应内容已保留'); } }); }
  if ($('#notifications-list')) {
    let next = 0;
    async function notifications() {
      const more = $('#notifications-more'); more.disabled = true;
      try { await ready; const data = await api('notifications', { before: next }, 'GET');
        if (!data.items.length && !next) $('#notifications-list').append(el('section', 'empty-state', '还没有新回应。下次回来，也许就有一点小惊喜。'));
        data.items.forEach(item => { const button = el('button', 'notification' + (!item.read_at ? ' is-unread' : '')); button.type = 'button'; button.append(el('span', '', item.kind === 'like' ? '♡' : '↗')); const text = el('div'); text.append(el('b', '', (item.username || item.user) + (item.kind === 'like' ? ' 喜欢了你的日常' : ' 留下了一句回应'))); const time = el('time'); time.dateTime = item.created_at.replace(' ', 'T') + 'Z'; text.append(time); button.append(text); button.onclick = async () => { button.disabled = true; try { await api('read', { id: item.id }); location.assign('/moments.php?id=' + item.moment_id + '#replies'); } catch (error) { button.disabled = false; toast(error.message); } }; $('#notifications-list').append(button); });
        next = data.next; more.hidden = !next; times($('#notifications-list'));
      } catch (error) { toast(error.message); } finally { more.disabled = false; }
    }
    $('#notifications-more').onclick = notifications; notifications();
  }
  document.addEventListener('click', async event => {
    const button=event.target.closest('[data-photo-restore]'); if(!button||button.disabled)return;
    button.disabled=true;
    try { await ready; await api('restoreImage',{id:Number(button.dataset.photoRestore)}); location.reload(); }
    catch(error){toast(error.message);button.disabled=false;}
  });
  const removedMore=$('#removed-more');
  if(removedMore)removedMore.addEventListener('click',async()=>{
    removedMore.disabled=true;
    try { await ready; const data=await api('removedImages',{before:removedMore.dataset.next},'GET'); for(const item of data.items){const box=el('article','panel removed-photo-card');if(!item.parentDeleted){const image=el('img');image.src=item.url;image.alt=item.name;image.loading='lazy';box.append(image);}const content=el('div');content.append(el('p','',item.name));const expiry=el('time');expiry.dateTime=item.expiresAt;content.append(el('small','','恢复期限：'),expiry);if(item.parentDeleted)content.append(link('/moments.php?view=trash','先恢复所属日常'));else{const restore=el('button','secondary-button','恢复到这条日常');restore.type='button';restore.dataset.photoRestore=item.id;content.append(restore,link('/moments.php?id='+item.momentId,'查看所属日常'));}box.append(content);times(box);$('#removed-photos').append(box);}removedMore.dataset.next=data.next||'';removedMore.hidden=!data.next;
    }catch(error){toast(error.message);}finally{removedMore.disabled=false;}
  });
})();
