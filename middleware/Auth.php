<?php

function requireLogin(): void {
    if (!isLoggedIn()) {
        redirect('?action=login');
    }
}
