<?php
if ($this->request->prefix !== 'default' && !empty($this->request->prefix)) {
    $this->url_prefix = $this->request->prefix . '/';
}

if ($this->request->prefix === 'admin') {
    $this->layout = 'admin';
    if (!$this->Session->isLogged() || $this->Session->user('rank')->level_admin <= 0) {
        $this->redirect('user/login');
    }
}

if ($this->request->prefix === 'user') {
    $this->layout = 'user';
    if (!$this->Session->isLogged()) {
        $this->redirect('user/login');
    }
}
?>
