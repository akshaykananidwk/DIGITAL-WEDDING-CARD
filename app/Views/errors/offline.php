<?php
$view->extend('layouts.plain', ['title' => 'Offline']);
?>
<div class="d-flex align-items-center justify-content-center min-vh-100 p-4">
    <div class="text-center" style="max-width:32rem">
        <i class="bi bi-wifi-off" style="font-size:3rem;color:var(--sk-muted)" aria-hidden="true"></i>
        <h1 class="h4 mt-3 mb-2">You are offline</h1>
        <p class="text-muted">
            Your invitations are safe. Reconnect and this page will load again.
        </p>
        <button class="btn btn-primary mt-2" type="button" onclick="window.location.reload()">Try again</button>
    </div>
</div>
