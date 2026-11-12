<?php
// includes/profile_photo_form.php - Profile photo with Change / Remove (used by both profile pages)
// Expects: $photoType ('student'|'admin'), $photoOwnerId, $photoName, $photoClass
$photoVersion = profilePhotoVersion($pdo, $photoType, (int)$photoOwnerId);
?>
<form action="../actions/profile_photo_action.php" method="POST" enctype="multipart/form-data" id="profilePhotoForm">
  <?= csrfField() ?>
  <input type="hidden" name="action" value="upload" id="profilePhotoAction">
  <div class="avatar-edit" id="profilePhotoWrap">
    <?= avatarHtml($photoType, (int)$photoOwnerId, $photoName, $photoVersion, $photoClass) ?>
    <label class="avatar-edit-btn" for="profilePhotoInput" title="<?= $photoVersion ? 'Change photo' : 'Upload a photo' ?>">
      <?= icon('camera', '', 15) ?>
      <span class="sr-only"><?= $photoVersion ? 'Change photo' : 'Upload a photo' ?></span>
    </label>
    <input type="file" name="photo" id="profilePhotoInput" accept="image/jpeg,image/png" hidden>
  </div>
  <div class="photo-actions">
    <button type="button" onclick="document.getElementById('profilePhotoInput').click()"><?= $photoVersion ? 'Change photo' : 'Upload photo' ?></button>
    <?php if ($photoVersion): ?>
      <button type="button" class="photo-remove" id="profilePhotoRemove">Remove</button>
    <?php endif; ?>
  </div>
  <div class="photo-hint">JPG or PNG, up to <?= PROFILE_PHOTO_MAX_BYTES / 1024 / 1024 ?> MB</div>
</form>
<script>
(function () {
  var form = document.getElementById('profilePhotoForm');
  var input = document.getElementById('profilePhotoInput');
  var remove = document.getElementById('profilePhotoRemove');
  input.addEventListener('change', function () {
    var file = input.files && input.files[0];
    if (!file) return;
    if (!/^image\/(jpeg|png)$/.test(file.type)) {
      alert('Please choose a JPG or PNG photo.');
      input.value = '';
      return;
    }
    if (file.size > <?= PROFILE_PHOTO_MAX_BYTES ?>) {
      alert('The photo is too large. The limit is <?= PROFILE_PHOTO_MAX_BYTES / 1024 / 1024 ?> MB.');
      input.value = '';
      return;
    }
    document.getElementById('profilePhotoWrap').classList.add('is-uploading');
    form.submit();
  });
  if (remove) {
    remove.addEventListener('click', function () {
      if (!confirm('Remove your profile photo?')) return;
      document.getElementById('profilePhotoAction').value = 'remove';
      form.submit();
    });
  }
})();
</script>
