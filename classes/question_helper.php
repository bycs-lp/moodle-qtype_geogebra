<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Helper class for geogebra questions with randomized variables and constraints.
 *
 * @package    qtype_geogebra
 * @copyright  2026 ISB Bayern
 * @author     Fabian Barbuia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_geogebra;

use stdClass;

/**
 * Helper class for geogebra questions with randomized variables and constraints.
 *
 * @package    qtype_geogebra
 * @copyright  (c) International GeoGebra Institute 2014
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_helper {

    /** @var string Regex pattern for inequality syntax validation. */
    private const INEQUALITY_PATTERN = '/^([a-z_0-9]+)(<=|<|>=|>)([a-z_0-9]+)$/i';

    /**
     * Checks if inequality is syntactically correct (i.e.: of the form a<b,a<=b,a>b,a>=b).
     *
     * @param string $inequality The inequality string to validate.
     * @return bool True if the $inequality is syntactically valid.
     */
    public static function is_valid_inequality(string $inequality): bool {
        $ret = preg_match(self::INEQUALITY_PATTERN, $inequality);
        return (bool) $ret;
    }

    /**
     * Checks if vars in inequality is part of randomizedvars.
     *
     * @param string $inequality Valid inequality, check first with {@see is_valid_inequality}.
     * @param string $randomizedvar Comma separated list of variables to be randomized.
     * @return bool True if all variables in the $inequality are part of the randomizedvars, false otherwise.
     */
    public static function is_valid_inequality_for_randomizedvars(string $inequality, string $randomizedvar): bool {
        $ret = false;
        if (preg_match(self::INEQUALITY_PATTERN, $inequality, $matches)) {
            $vars = array_map('trim', explode(',', $randomizedvar));
            $ret = in_array($matches[1], $vars, true) && in_array($matches[3], $vars, true);
        }
        return $ret;
    }

    /**
     * Given the inequality is valid for the question, we check if the inequalities aren't contradictory
     * to the sliders min and max values.
     *
     * @param string $inequality Valid inequality for this question.
     * @param string $randomizedvar Comma separated list of variables to be randomized.
     * @param string $ggbxml The ggbxml of the question applet.
     * @return bool True if everything is ok.
     */
    public static function is_valid_inequality_for_slider_minmax(
        string $inequality,
        string $randomizedvar,
        string $ggbxml
    ): bool {
        $ret = false;
        if (preg_match(self::INEQUALITY_PATTERN, $inequality, $matches)) {
            $vars = self::get_variables_with_minmaxstep($randomizedvar, $ggbxml);
            $op = $matches[2];
            if (isset($vars[$matches[1]], $vars[$matches[3]])) {
                $ret = self::check_inequality($op, $vars[$matches[1]]['min'], $vars[$matches[3]]['max'])
                    || self::check_inequality($op, $vars[$matches[1]]['max'], $vars[$matches[3]]['min']);
                // Refine: check the actual semantics.
                $ret = match ($op) {
                    '<' => $vars[$matches[1]]['min'] < $vars[$matches[3]]['max'],
                    '<=' => $vars[$matches[1]]['min'] <= $vars[$matches[3]]['max'],
                    '>' => $vars[$matches[1]]['max'] > $vars[$matches[3]]['min'],
                    '>=' => $vars[$matches[1]]['max'] >= $vars[$matches[3]]['min'],
                    default => false,
                };
            }
        }
        return $ret;
    }

    /**
     * Extract the min, max and step for the variables used from the xml.
     *
     * @param string $randomizedvar Comma separated list of variables to be randomized.
     * @param string $ggbxml The ggbxml of the question applet.
     * @return array A two dimensional array indexed by the label of the var containing an array with min, max and step.
     */
    public static function get_variables_with_minmaxstep(string $randomizedvar, string $ggbxml): array {
        $vars = array_filter(array_map('trim', explode(',', $randomizedvar)));
        $varswithminmaxstep = [];
        $xml = simplexml_load_string($ggbxml);
        if ($xml === false) {
            return [];
        }
        foreach ($xml->construction->element as $elem) {
            foreach ($vars as $label) {
                if ((string) $label === (string) $elem['label']) {
                    $varswithminmaxstep[$label] = [
                        'min' => (float) $elem->slider['min'],
                        'max' => (float) $elem->slider['max'],
                        'increment' => (float) $elem->animation['step'],
                    ];
                }
            }
        }
        return $varswithminmaxstep;
    }

    /**
     * Checks if two numbers meet an inequality.
     *
     * @param string $op The operator of the form <, <=, >=, >.
     * @param int|float $x The value of the first variable in the inequality.
     * @param int|float $y The value of the second variable in the inequality.
     * @return bool Result of $x $op $y.
     */
    public static function check_inequality(string $op, int|float $x, int|float $y): bool {
        return match ($op) {
            '<' => $x < $y,
            '<=' => $x <= $y,
            '>' => $x > $y,
            '>=' => $x >= $y,
            default => false,
        };
    }

    /**
     * Randomize all variables in the question given the constraints.
     *
     * @param array $vars As returned by {@see question_helper::get_variables_with_minmaxstep}, passed by reference.
     * @param array $inequalities Valid inequalities for this question.
     * @param float $timelimit Maximum time allowed for creation. Default: INF.
     * @return stdClass|null Null on success, stdClass with fields time and tries on failure.
     */
    public static function randomize_vars(array &$vars, array $inequalities, float $timelimit = INF): ?stdClass {
        self::set_random_values($vars);

        if (count($inequalities) > 0) {
            $i = 0;
            $combinationfound = false;
            $time = -microtime(true);
            while (!$combinationfound && ($time + microtime(true) < $timelimit)) {
                $combinationfound = true;
                foreach ($inequalities as $inequality) {
                    if (!isset($inequality[1], $inequality[2], $inequality[3])) {
                        continue;
                    }
                    if (!isset($vars[$inequality[1]], $vars[$inequality[3]])) {
                        $combinationfound = false;
                        break;
                    }
                    $combinationfound = self::check_inequality(
                        $inequality[2],
                        $vars[$inequality[1]]['val'],
                        $vars[$inequality[3]]['val']
                    );
                    if (!$combinationfound) {
                        self::set_random_values($vars);
                        break;
                    }
                }
                $i++;
            }
            if (!$combinationfound) {
                $a = new stdClass();
                $a->time = $time + microtime(true);
                $a->tries = $i;
                return $a;
            }
        }
        return null;
    }

    /**
     * Used by {@see set_random_values} for calculating random values valid for the slider definition.
     *
     * @param int|float $min Min value.
     * @param int|float $max Max value.
     * @param int|float $increment Step size.
     * @return int|float Random number in the set {x | $min <= x <= $max, x = $min + n * $increment, n in N}.
     */
    public static function random_incremented_value(int|float $min, int|float $max, int|float $increment): int|float {
        if ($increment <= 0) {
            return $min;
        }
        return $min + mt_rand(0, (int) (($max - $min) / $increment)) * $increment;
    }

    /**
     * Sets all variables to a random incremented value.
     *
     * @param array $vars As returned by {@see question_helper::get_variables_with_minmaxstep}, passed by reference.
     */
    private static function set_random_values(array &$vars): void {
        foreach ($vars as &$var) {
            $var['val'] = self::random_incremented_value($var['min'], $var['max'], $var['increment']);
        }
    }
}
