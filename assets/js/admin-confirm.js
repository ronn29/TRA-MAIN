document.addEventListener('DOMContentLoaded', () => {
  // Create modal
  let modal = document.getElementById('confirm-delete-modal');
  if (!modal) {
    modal = document.createElement('div');
    modal.id = 'confirm-delete-modal';
    modal.innerHTML = `
      <div class="confirm-overlay">
        <div class="confirm-dialog">
          <h3>Are you sure?</h3>
          <p class="confirm-message">This action cannot be undone.</p>
          <div class="confirm-actions">
            <button type="button" class="btn-confirm-cancel">Cancel</button>
            <button type="button" class="btn-confirm-yes">Delete</button>
          </div>
        </div>
      </div>
    `;
    document.body.appendChild(modal);
  }
  const overlay = modal.querySelector('.confirm-overlay');
  const messageEl = modal.querySelector('.confirm-message');
  const cancelBtn = modal.querySelector('.btn-confirm-cancel');
  const yesBtn = modal.querySelector('.btn-confirm-yes');
  let pendingAction = null;

  const closeModal = () => {
    modal.classList.remove('open');
    pendingAction = null;
  };

  cancelBtn.addEventListener('click', closeModal);
  overlay.addEventListener('click', (e) => {
    if (e.target === overlay) closeModal();
  });

  yesBtn.addEventListener('click', () => {
    if (!pendingAction) return closeModal();
    const { href, submitForm } = pendingAction;
    closeModal();
    if (submitForm) {
      submitForm.submit();
    } else if (href) {
      window.location.href = href;
    }
  });

  function bindConfirm(el) {
    el.addEventListener('click', (e) => {
      const href = el.getAttribute('href');
      const confirmText = el.dataset.confirmMessage || 'This action cannot be undone.';
      const submitForm = el.dataset.submitForm ? document.getElementById(el.dataset.submitForm) : null;

      if (!href && !submitForm) {
        return; // nothing to do
      }

      e.preventDefault();
      messageEl.textContent = confirmText;
      pendingAction = { href, submitForm };
      modal.classList.add('open');
    });
  }

  // Targets: elements with class confirm-delete or data-confirm-delete
  document.querySelectorAll('.confirm-delete, [data-confirm-delete="true"]').forEach(bindConfirm);
});

// Allow other scripts to trigger confirm programmatically
window.triggerDeleteConfirm = function (message, onYes) {
  const modal = document.getElementById('confirm-delete-modal');
  if (!modal) return onYes && onYes();
  const messageEl = modal.querySelector('.confirm-message');
  const yesBtn = modal.querySelector('.btn-confirm-yes');
  modal.classList.add('open');
  messageEl.textContent = message || 'Are you sure?';
  const handler = () => {
    yesBtn.removeEventListener('click', handler);
    modal.classList.remove('open');
    if (onYes) onYes();
  };
  yesBtn.addEventListener('click', handler);
};
