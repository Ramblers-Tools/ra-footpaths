<?php
/**
 * @package     com_ra_footpaths
 * @copyright   2026 East Cheshire Ramblers
 * @license     GNU General Public License version 2 or later
 */

namespace Ramblers\Component\Ra_footpaths\Site\Helper;

\defined('_JEXEC') or die;

/**
 * Small GeoJSON helpers for the app endpoints, done in PHP so they don't depend on the database's
 * spatial functions (MariaDB on dev, maybe MySQL on live). Coordinates are WGS84 [lng, lat].
 */
final class Geo
{
	/**
	 * A Polygon or MultiPolygon with every ring simplified (Douglas-Peucker) to about $metres, keeping
	 * at least 4 points per ring, and rounded to 5 decimal places. Other geometries come back rounded.
	 */
	public static function simplify(array $geometry, float $metres = 10.0): array
	{
		$type = $geometry['type'] ?? '';
		$coords = $geometry['coordinates'] ?? [];

		if ($type === 'Polygon') {
			$coords = array_map(static fn ($ring) => self::simplifyRing($ring, $metres), $coords);
		} elseif ($type === 'MultiPolygon') {
			$coords = array_map(static fn ($poly) => array_map(static fn ($ring) => self::simplifyRing($ring, $metres), $poly), $coords);
		} else {
			$coords = self::round($coords);
		}

		return ['type' => $type, 'coordinates' => $coords];
	}

	/**
	 * A point to place the label: the area-weighted centroid of the largest polygon's outer ring,
	 * as [lng, lat] to 5 decimal places. Null for an empty or unknown geometry.
	 */
	public static function centre(array $geometry): ?array
	{
		$type = $geometry['type'] ?? '';
		$polygons = $type === 'Polygon' ? [$geometry['coordinates'] ?? []]
			: ($type === 'MultiPolygon' ? ($geometry['coordinates'] ?? []) : []);
		$best = null;
		$bestArea = -1.0;

		foreach ($polygons as $polygon) {
			$ring = $polygon[0] ?? [];
			[$area, $x, $y] = self::ringCentroid($ring);

			if (abs($area) > $bestArea) {
				$bestArea = abs($area);
				$best = [$x, $y];
			}
		}

		return $best === null ? null : [round($best[0], 5), round($best[1], 5)];
	}

	/** [lng, lat] rounded, or null when missing or 0 (the old system's "no position"). */
	public static function position($lat, $lng): ?array
	{
		if ($lat === null || $lng === null || (float) $lat === 0.0 || (float) $lng === 0.0) {
			return null;
		}

		return [round((float) $lng, 5), round((float) $lat, 5)];
	}

	private static function round(array $coords): array
	{
		if (isset($coords[0]) && !\is_array($coords[0])) {
			return [round((float) $coords[0], 5), round((float) $coords[1], 5)];
		}

		return array_map([self::class, 'round'], $coords);
	}

	/** @return array{0: float, 1: float, 2: float} signed area (degrees²), centroid x, centroid y */
	private static function ringCentroid(array $ring): array
	{
		$n = \count($ring);

		if ($n < 3) {
			return [0.0, (float) ($ring[0][0] ?? 0), (float) ($ring[0][1] ?? 0)];
		}

		$area = 0.0;
		$cx = 0.0;
		$cy = 0.0;

		for ($i = 0; $i < $n - 1; $i++) {
			[$x0, $y0] = $ring[$i];
			[$x1, $y1] = $ring[$i + 1];
			$cross = $x0 * $y1 - $x1 * $y0;
			$area += $cross;
			$cx += ($x0 + $x1) * $cross;
			$cy += ($y0 + $y1) * $cross;
		}

		if (abs($area) < 1e-12) {
			return [0.0, (float) $ring[0][0], (float) $ring[0][1]];
		}

		$area /= 2;

		return [$area, $cx / (6 * $area), $cy / (6 * $area)];
	}

	private static function simplifyRing(array $ring, float $metres): array
	{
		$n = \count($ring);

		if ($n <= 4) {
			return self::round($ring);
		}

		// Work in local metres so the tolerance means the same east-west as north-south.
		$lat = deg2rad((float) $ring[0][1]);
		$kx = 111320 * cos($lat);
		$ky = 110540;
		$pts = array_map(static fn ($p) => [(float) $p[0] * $kx, (float) $p[1] * $ky], $ring);

		$keep = array_fill(0, $n, false);
		$keep[0] = $keep[$n - 1] = true;
		$stack = [[0, $n - 1]];

		while ($stack) {
			[$a, $b] = array_pop($stack);
			$max = 0.0;
			$index = -1;

			for ($i = $a + 1; $i < $b; $i++) {
				$d = self::distance($pts[$i], $pts[$a], $pts[$b]);

				if ($d > $max) {
					$max = $d;
					$index = $i;
				}
			}

			if ($index !== -1 && $max > $metres) {
				$keep[$index] = true;
				$stack[] = [$a, $index];
				$stack[] = [$index, $b];
			}
		}

		$out = [];

		foreach ($ring as $i => $p) {
			if ($keep[$i]) {
				$out[] = $p;
			}
		}

		// A closed ring needs at least 4 points; fall back to the original if simplification went too far.
		return self::round(\count($out) >= 4 ? $out : $ring);
	}

	private static function distance(array $p, array $a, array $b): float
	{
		$dx = $b[0] - $a[0];
		$dy = $b[1] - $a[1];
		$len = $dx * $dx + $dy * $dy;

		if ($len == 0.0) {
			return hypot($p[0] - $a[0], $p[1] - $a[1]);
		}

		$t = max(0, min(1, (($p[0] - $a[0]) * $dx + ($p[1] - $a[1]) * $dy) / $len));

		return hypot($p[0] - ($a[0] + $t * $dx), $p[1] - ($a[1] + $t * $dy));
	}
}
