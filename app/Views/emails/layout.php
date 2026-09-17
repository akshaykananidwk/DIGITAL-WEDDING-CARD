<?php
/**
 * Shared email shell.
 *
 * Table-based and inline-styled, because that is what mail clients render
 * reliably. No images, no web fonts and no tracking pixels.
 *
 * @var string $subject
 * @var string $appName
 * @var string $appUrl
 * @var string $content
 */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($subject) ?></title>
</head>
<body style="margin:0;padding:0;background:#FFF8EE;font-family:-apple-system,'Segoe UI',Roboto,Arial,sans-serif;color:#4A3728;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#FFF8EE;padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                   style="max-width:560px;background:#FFFFFF;border:1px solid #E8DBC8;border-radius:14px;overflow:hidden;">
                <tr>
                    <td style="padding:20px 24px;background:#C8102E;color:#FFF3E2;">
                        <span style="font-size:18px;font-weight:700;letter-spacing:.01em;"><?= e($appName) ?></span>
                    </td>
                </tr>
                <tr>
                    <td style="padding:24px;font-size:15px;line-height:1.6;">
                        <?= $content ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px 24px;background:#F6EADA;font-size:12px;color:#7A6A55;">
                        <a href="<?= e($appUrl) ?>" style="color:#C8102E;text-decoration:none;"><?= e($appUrl) ?></a><br>
                        You received this email because someone used this address on <?= e($appName) ?>.
                        If it was not you, no action is needed.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
