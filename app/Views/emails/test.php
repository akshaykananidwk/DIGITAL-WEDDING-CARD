<?php
/**
 * @var string $appName
 * @var string $driver
 */
$view->extend('emails.layout');
?>
<p style="margin:0 0 14px;">This is a test email from <strong><?= e($appName) ?></strong>.</p>

<p style="margin:0 0 14px;">
    If you are reading this, outgoing email works. Delivery used the
    <strong><?= e($driver) ?></strong> driver.
</p>

<p style="margin:0;font-size:13px;color:#7A6A55;">
    Sent <?= e(date('d M Y, H:i')) ?> (Asia/Kolkata) from the admin settings screen.
</p>
