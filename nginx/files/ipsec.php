<?php
$output = shell_exec('swanctl --stats | grep IKE_SAs && echo && swanctl --list-sas');
echo "<pre>$output</pre>";
?>
