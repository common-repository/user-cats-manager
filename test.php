<?php

global $testCase, $testCount;
$testCase = array ();
$testCount = 0;

function testCase ($espera, $saida, $mensagem='') {
  global $testCase, $testCount;
  $testCount++;
  if ($espera === $saida) {
    print '.';
    return true;
  }
  print 'F';
  $espera_tipo = gettype($espera);
  if ($espera_tipo == 'boolean')
    if ($espera === true) $espera = 'true';
    else $espera = 'false';
  if ($espera_tipo == 'array')
    $espera = print_r($espera, true);

  $saida_tipo = gettype($saida);
  if ($saida_tipo == 'boolean')
    if ($saida === true) $saida = 'true';
    else $saida = 'false';
  if ($saida_tipo == 'array')
    $saida = print_r($saida, true);

  $testCase[] = <<<EOF

FAIL: $mensagem
=======================================
esperava: ($espera_tipo)
$espera
saida: ($saida_tipo)
$saida

EOF;
}


function testResume () {
  global $testCase, $testCount;
  print "\n-----------------------\nTestes feitos: $testCount";
  if (count($testCase) === 0) {
    print "\n-----------------------\n\nOK";
  } else {
    print ' - Falhas: ' . count($testCase) . "\n";
    print implode("\n", $testCase);
  }
}
?>