<?php

function isoNow(){
    return date('Y-m-d\TH:i:s');
}

/* Returns the difference in seconds (warning: only accurate +/-1second!)*/
function timeSub($minuend, $subtrahend){
    $dateMinuend    = new DateTime(preg_replace('/T/', ' ', $minuend));
    $dateSubtrahend = new DateTime(preg_replace('/T/', ' ', $subtrahend));
    return $dateMinuend->getTimestamp() - $dateSubtrahend->getTimestamp();
}

?>
