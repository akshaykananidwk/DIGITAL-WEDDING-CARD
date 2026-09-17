<?php
/**
 * @var string $toName
 * @var string $resetUrl
 * @var int $minutes
 */
$view->extend('emails.layout');
?>
<p style="margin:0 0 14px;">Namaste <?= e($toName) ?>,</p>

<p style="margin:0 0 14px;">
    We received a request to reset your password. Choose a new one using the button below.
    This link works once and expires in <?= (int) $minutes ?> minutes.
</p>

<p style="margin:0 0 20px;">
    <a href="<?= e($resetUrl) ?>"
       style="display:inline-block;padding:12px 20px;background:#C8102E;color:#FFFFFF;border-radius:10px;text-decoration:none;font-weight:700;">
        Choose a new password
    </a>
</p>

<p style="margin:0 0 6px;font-size:13px;color:#7A6A55;">Or copy this link into your browser:</p>
<p style="margin:0 0 14px;font-size:13px;word-break:break-all;">
    <a href="<?= e($resetUrl) ?>" style="color:#C8102E;"><?= e($resetUrl) ?></a>
</p>

<p style="margin:0;font-size:13px;color:#7A6A55;">
    If you did not ask to reset your password, ignore this email — your current password stays active and
    nobody can use this link without access to your inbox.
</p>
