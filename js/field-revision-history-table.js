/**
 * @file
 * Modal options and animation used by Field Revision History module.
 */
(function (Drupal, once) {
  Drupal.behaviors.fieldRevisionHistoryTable = {
    attach: function (context, settings) {
      once('field-revision-history-link', '.field-revision-history-link', context).forEach(function(link) {
        link.addEventListener('click', function(e) {
          e.preventDefault();

          // Set variables.
          const table = link.closest('.field-revision-history-table');
          const modal = link.closest('.ui-dialog');
          const startHeight = modal.offsetHeight;

          // Hide action link.
          link.closest('caption').style.display = 'none';

          // Show hidden table rows.
          table.querySelectorAll('.field-revision-history-unchanged').forEach(function(tr) {
            tr.style.display = 'table-row';
            tr.style.overflow = 'hidden';
            tr.style.height = '0';
            tr.style.opacity = '0';
            tr.style.transition = 'height 400ms, opacity 400ms';
            tr.style.visibility = 'hidden';
            tr.style.height = 'auto';
            tr.style.height = '0';
            tr.style.visibility = 'visible';
            setTimeout(function() {
              tr.style.height = tr.offsetHeight + 'px';
              tr.style.opacity = '1';
            }, 10);
            tr.addEventListener('transitionend', function handler(e) {
              if (e.propertyName === 'height') {
                tr.style.height = '';
                tr.style.overflow = '';
                tr.style.transition = '';
                tr.removeEventListener('transitionend', handler);
              }
            });
          });

          // Animate modal height update.
          modal.style.height = startHeight + 'px';
          modal.style.transition = 'height 0.3s ease';
          requestAnimationFrame(function(){
            const finalHeight = modal.scrollHeight;
            modal.style.height = finalHeight + 'px';
          });
          modal.addEventListener('transitionend', function handler(e){
            if (e.propertyName === "height") {
              modal.style.height = 'auto';
              modal.style.transition = '';
              modal.removeEventListener('transitionend', handler);
            }
          });
          modal.style.left = '50%';
          modal.style.top = '50%';
          modal.style.transform = 'translate(-50%, -50%)';
        }, { once: true });
      });
    }
  };
})(Drupal, once);
