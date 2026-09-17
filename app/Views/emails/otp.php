<?php
/**
 * @var string $toName
 * @var string $code
 * @var int $minutes
 */
$view->extend('emails.layout');
?>
<p style="margin:0 0 14px;">Namaste <?= e($toName) ?>,</p>

<p style="margin:0 0 14px;">Your sign-in code is:</p>

<p style="margin:0 0 18px;">
    <span style="display:inline-block;padding:12px 22px;background:#FFF3E2;border:1px solid #E8DBC8;
                 border-radius:10px;font-size:26px;font-weight:700;letter-spacing:.32em;color:#2E2118;">
        <?= e($code) ?>
    </span>
</p>

<p style="margin:0 0 14px;">
    It expires in <?= (int) $minutes ?> minutes and can be used once.
</p>

<p style="margin:0;font-size:13px;color:#7A6A55;">
    If you did not try to sign in, someone may have your password: change it as soon as
    you can. We will never ask you for this code.
</p>
