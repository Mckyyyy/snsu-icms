<?php
// includes/footer.php - App Footer and Global Modals (Figure 9)
$isSubdir = (strpos($_SERVER['SCRIPT_NAME'], '/student/') !== false || strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false);
$rootPrefix = $isSubdir ? '../' : './';
$assetPrefix = $isSubdir ? '../assets/' : 'assets/';
?>
  </div><!-- /.main-content-container -->
</main>
</div><!-- /.app-container -->

<!-- Figure 9: Secure Logout Confirmation Modal -->
<div class="modal-backdrop" id="logoutModal" onclick="if(event.target === this) closeModal('logoutModal')">
  <div class="modal-content modal-confirm-content" style="text-align: center;">
    <div class="modal-body" style="padding: 36px 24px 24px 24px;">
      <div class="confirm-icon-wrap confirm-icon-info">
        <?= icon('lock', '', 30) ?>
      </div>
      <h3 style="font-size: 1.25rem; font-weight: 700; color: #0f172a; margin-bottom: 8px;">Logout Confirmation</h3>
      <p style="font-size: 0.9rem; color: #64748b; margin-bottom: 24px; line-height: 1.5;">
        Are you sure you want to logout?<br>You will be redirected to the login page.
      </p>
      
      <div class="modal-confirm-actions">
        <button type="button" class="btn btn-secondary" style="min-width: 110px;" onclick="closeModal('logoutModal')">Cancel</button>
        <form action="<?= $rootPrefix ?>logout.php" method="POST" style="margin: 0;">
          <?= csrfField() ?>
          <button type="submit" class="btn btn-primary" style="min-width: 110px; background-color: #2563eb;">Logout</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Universal Action Confirmation Modal (Delete / Deactivate / State Changes) -->
<div class="modal-backdrop" id="confirmationModal" onclick="if(event.target === this) closeModal('confirmationModal')">
  <div class="modal-content modal-confirm-content" style="text-align: center;">
    <div class="modal-body" style="padding: 32px 24px 20px 24px;">
      <div id="confirmModalIconWrap" class="confirm-icon-wrap confirm-icon-danger">
        <?= icon('alert-triangle', '', 32) ?>
      </div>
      <h3 id="confirmModalTitle" style="font-size: 1.25rem; font-weight: 700; color: #0f172a; margin-bottom: 8px;">Confirm Action</h3>
      <p id="confirmModalMessage" style="font-size: 0.9rem; color: #64748b; margin-bottom: 24px; line-height: 1.5;">
        Are you sure you want to proceed with this action? This cannot be undone.
      </p>
      
      <div class="modal-confirm-actions" id="confirmModalButtons">
        <button type="button" class="btn btn-secondary" style="min-width: 110px;" onclick="closeModal('confirmationModal')">Cancel</button>
        <a href="#" id="confirmModalActionBtn" class="btn btn-primary" style="min-width: 110px; background-color: #dc2626; border-color: #dc2626;">Confirm</a>
      </div>
    </div>
  </div>
</div>

<!-- Global Toast Notification Container (Top-Right Upper Corner) -->
<div class="toast-container" id="toastContainer"></div>

<!-- Floating Messages button + chat panel (bottom-right) -->
<?php require __DIR__ . '/chat_widget.php'; ?>

</div><!-- /.app-wrapper -->

<!-- Core Scripts -->
<script src="<?= $assetPrefix ?>js/main.js<?= assetVersion('js/main.js') ?>"></script>
<script src="<?= $assetPrefix ?>js/filter.js<?= assetVersion('js/filter.js') ?>"></script>
<script src="<?= $assetPrefix ?>js/messages.js<?= assetVersion('js/messages.js') ?>"></script>
<script src="<?= $assetPrefix ?>js/chat.js<?= assetVersion('js/chat.js') ?>"></script>
</body>
</html>
