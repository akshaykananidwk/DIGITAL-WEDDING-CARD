<?php
/**
 * @var string $toName
 * @var string $verifyUrl
 */
$view->extend('emails.layout');
?>
<p style="margin:0 0 14px;">Namaste <?= e($toName) ?>,</p>

<p style="margin:0 0 14px;">
    Thank you for creating an account. Please confirm this email address so we can keep your invitations
    safe and let you recover your account if you ever forget your password.
</p>

<p style="margin:0 0 20px;">
    <a href="<?= e($verifyUrl) ?>"
       style="display:inline-block;padding:12px 20px;background:#C8102E;color:#FFFFFF;border-radius:10px;text-decoration:none;font-weight:700;">
        Confirm my email
    </a>
</p>

<p style="margin:0 0 6px;font-size:13px;color:#7A6A55;">Or copy this link into your browser:</p>
<p style="margin:0 0 14px;font-size:13px;word-break:break-all;">
    <a href="<?= e($verifyUrl) ?>" style="color:#C8102E;"><?= e($verifyUrl) ?></a>
</p>

<p style="margin:0;font-size:13px;color:#7A6A55;">
    If you did not create this account, you can ignore this email and nothing further will happen.
</p>
