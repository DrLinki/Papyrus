<?php use Config\Conf; ?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="charset=utf-8" />
		<title><?php echo $title_for_layout ?? Conf::$settings->htmlheader->resume ?? ''; ?></title>
	</head>
	<body id="<?php echo $this->request->controller.'_'.$this->request->action; ?>" class="<?php echo $this->layout.'_layout'; ?>">
		<div id="container" class="<?php echo $this->request->controller.'_page'; ?>">
			<div class="content" id="<?php echo $this->request->controller; ?>_content">
				<?php echo $this->Session->flash(); ?>
				<?php echo isset($content_for_layout)?$content_for_layout:'Papyrus'; ?>
			</div>
			<div style="clear:both;"></div>
		</div>
    </body>
</html>