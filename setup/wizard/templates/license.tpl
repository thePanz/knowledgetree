<form id="welcome_license_dependencies" action="index.php?step_name=license" method="POST">
<p class="title">License Agreement</p>

<div class="error_message">
    <?php if(isset($errors)) {
        foreach($errors as $k=>$e) {
                echo $e;
        }
    }?>
</div>
<p class="description">
Please read and accept the license agreement below before continuing with the setup.
</p>

<div class="license_agreement" tabindex="-1">
<?php echo file_get_contents($licensePath); ?>
</div>
	<input id="accept" type="hidden" name="license" value=""/>
	<input type="submit" name="Previous" value="Previous" class="button_previous"/>
	<input type="submit" name="Next" value="I Agree" onclick="javascript:{document.getElementById('accept').value = 1;}" class="button_next"/>
    <input type="submit" name="Next" value="I Disagree" onclick="javascript:{document.getElementById('accept').value = 0;}" class="button_next"/>
</form>
<?php if (AJAX) { echo $html->js('form.js'); } ?>