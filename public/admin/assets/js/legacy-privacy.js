(() => {
  const csrf = document.currentScript.dataset.csrf;
  document.addEventListener('click', async event => {
    const button = event.target.closest('[data-legacy-save]');
    if (!button || button.disabled) return;
    const control = button.closest('.legacy-visibility-control');
    const status = control.querySelector('[role=status]');
    button.disabled = true; status.textContent = '保存中…';
    try {
      const response = await fetch('/admin/legacy-visibility.php', {method:'POST', cache:'no-store', body:new URLSearchParams({kind:control.dataset.kind,id:control.dataset.id,visibility:control.querySelector('select').value,csrf})});
      const result = await response.json();
      if (!response.ok || !result.ok) throw new Error(result.message || '保存失败');
      status.textContent = '已保存';
    } catch (error) { status.textContent = error.message || '保存失败，请重试'; }
    finally { button.disabled = false; }
  });
})();
