<?php
require_once "../Matrix.php";

class LagrangeInterpolation
{
	public function findPolynomialFactors($x, $y) {
		$n = count($x);
		$data = array();
		$rhs  = array();
		for ($i = 0; $i < $n; ++$i) {
			$v = 1;
			for ($j = 0; $j < $n; ++$j) {
				$data[$i][$n-$j-1] = $v;
				$v *= $x[$i];
			}
			$rhs[$i] = $y[$i];
		}
		$m = new Matrix($data);
		$b = new Matrix($rhs, $n);
		$s = $m->solve($b);
		return $s->getRowPackedCopy();
	}
}

$x = array(2.0, 1.0, 3.0);
$y = array(3.0, 4.0, 7.0);
$li = new LagrangeInterpolation;
$f = $li->findPolynomialFactors($x, $y);
for ($i = 0; $i < 3; ++$i) echo $f[$i]."<br />";
