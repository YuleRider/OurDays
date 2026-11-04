(() => {
  const initialUser = document.currentScript.dataset.user;
  const removePrivateView = () => {
    document.querySelector('#pjax-container')?.replaceChildren();
    document.querySelectorAll('.spotlight').forEach(image => image.removeAttribute('src'));
    location.reload();
  };
  document.addEventListener('visibilitychange', async () => {
    if (document.hidden) return;
    try {
      const response = await fetch('/couple-api.php?action=bootstrap', {cache:'no-store'});
      const result = await response.json();
      if (response.ok && result.ok && String(result.data.user?.id || 0) !== initialUser) removePrivateView();
    } catch (_) {}
  });
  window.addEventListener('pageshow', event => { if (event.persisted) removePrivateView(); });
})();
