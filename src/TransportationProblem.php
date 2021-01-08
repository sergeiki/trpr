<?php

namespace sergeiki\trpr;

class TransportationProblem
{
    /**
     * @var array
     */
    private $storages;
    /**
     * @var array
     */
    private $shops;
    /**
     * @var int
     */
    private $shop_count;
    /**
     * @var int
     */
    private $storage_count;
    /**
     * @var array
     */
    private $costs;
    /**
     * @var array
     */
    private $u;
    /**
     * @var array
     */
    private $v;
    /**
     * @var int
     */
    private $plan_count;
    /**
     * @var string
     */
    private $optimality_criterion;
    /**
     * @var int
     */
    private $initial_shop_count;
    /**
     * @var int
     */
    private $initial_storage_count;


    public function __construct(array $storages, array $shops, array $costs)
    {
        $this->storages = $storages;
        $this->shops = $shops;
        $this->storage_count = $this->initial_storage_count = count($this->storages);
        $this->shop_count = $this->initial_shop_count = count($this->shops);
        $this->costs = $costs;
    }

    public function getBasePlan(): array
    {
        $plan = [];
        $degenerate = true;
        $storages = $this->getStorages();
        $shops = $this->getShops();

        for ($k = 0; $k < $this->storage_count; $k++) {
            $plan[$k] = array_fill(0, $this->shop_count, null);
        } //print_r($plan);

        for ($j = 0; $j < $this->storage_count; $j++) {
            for ($i = 0; $i < $this->shop_count; $i++) {

                // for degenerate base plan
                if ($plan[$j][$i] === 0) continue;

                if ($storages[$j] === 0 or $shops[$i] === 0) {
                    $plan[$j][$i] = null;
                }
                elseif ($storages[$j] > $shops[$i]) {
                    $plan[$j][$i] = $shops[$i];
                    $storages[$j] = $storages[$j] - $shops[$i];
                    $shops[$i] = 0;
                }
                elseif ($storages[$j] < $shops[$i]) {
                    $plan[$j][$i] = $storages[$j];
                    $shops[$i] = $shops[$i] - $storages[$j];
                    $storages[$j] = 0;
                }
                elseif ($storages[$j] === $shops[$i]) {
                    $plan[$j][$i] = $shops[$i];
                    $storages[$j] = $shops[$i] = 0;
                    // for degenerate base plan
                    if ($degenerate) {
                        $plan[$j][$i + 1] = 0;
                        $degenerate = false;
                    }
                }
            }
        }

        return $plan;
    }

    public function usePotentialMethod($plan)
    {
        // $u[], $v[] are potentials
        $u[0] = 0;
        $v = $uv = $evaluation_matrix = $color_evaluation_matrix = [];
        $costs = $this->costs;

        // repeating the cycle of calculating $u[] and $v[] as many times as necessary
        do {
            $undefined = false;
            for ($j = 0; $j < $this->storage_count; $j++) {
                for ($i = 0; $i < $this->shop_count; $i++) {
                    if ($plan[$j][$i] !== null) {
                        if (isset($u[$j])) $v[$i] = $costs[$j][$i] - $u[$j];
                        elseif (isset($v[$i])) $u[$j] = $costs[$j][$i] - $v[$i];
                        else $undefined = true;
                        $uv[$j][$i] = $costs[$j][$i];
                    }
                }
            }
        } while ($undefined === true); //print_r($u);

        // calculate null elements
        for ($j = 0; $j < $this->storage_count; $j++) {
            for ($i = 0; $i < $this->shop_count; $i++) {
                if ($plan[$j][$i] === null) {
                    $uv[$j][$i] = $u[$j] + $v[$i];
                }
            }
        }

        // calculate Dji
        for ($j = 0; $j < $this->storage_count; $j++) {
            for ($i = 0; $i < $this->shop_count; $i++) {
                if ($this->getOptimalityCriterion() === 'max') $evaluation_matrix[$j][$i] = $uv[$j][$i] - $costs[$j][$i];
                else $evaluation_matrix[$j][$i] = $costs[$j][$i] - $uv[$j][$i];
            }
        }

        $this->u = $u;
        $this->v = $v;

        return $evaluation_matrix;
    }

    public function getRecalculationCycle(array $plan, array $min)
    {
        $plan[$min['j']][$min['i']] = 0; //echo 'min='; print_r($min);
        $was_deleted = true;

        while ($was_deleted) {
            $was_deleted = false;

            // deleting rows with one element only
            $new_plan = [];
            foreach ($plan as $j => $row) {
                $el_co = 0;
                foreach ($row as $i => $el) {
                    if ($el !== null) $el_co++;
                }
                if ($el_co > 1) {
                    $new_plan[$j] = $plan[$j];
                }
                else {
                    $was_deleted = true;
                }
            }

            // overturning array for deleting cols
            $new_plan2 = [];
            foreach ($new_plan as $j => $row) {
                foreach ($row as $i => $el) {
                    $new_plan2[$i][$j] = $new_plan[$j][$i];
                }
            } //print_r($new_plan2);

            // deleting cols similar to deleting rows
            $new_plan3 = [];
            foreach ($new_plan2 as $j => $col) {
                $el_co = 0;
                foreach ($col as $i => $el) {
                    if ($el !== null) $el_co++;
                }
                if ($el_co > 1) {
                    $new_plan3[$j] = $new_plan2[$j];
                }
                else {
                    $was_deleted = true;
                }
            }

            // overturning array back
            $new_plan4 = [];
            foreach ($new_plan3 as $j => $row) {
                foreach ($row as $i => $el) {
                    $new_plan4[$i][$j] = $new_plan3[$j][$i];
                }
            }

            $plan = $new_plan4; //print_r($new_plan4);
        }

        // making normal serial array indexing
        $new_plan5 = $new_plan5_row = [];
        foreach ($new_plan4 as $j => $row) {
            $new_plan5_row = [];
            foreach ($row as $i => $el) {
                $new_plan5_row[] = [
                    'el' => $el === null ? ' ' : $el,
                    'j' => $j,
                    'i' => $i
                ];
            }
            $new_plan5[] = $new_plan5_row;
        }

        // getting normal serial array's coordinates for min element from which will begin cycle
        $current_j = $current_i = null;
        foreach ($new_plan5 as $j => $row)
            foreach ($row as $i => $el)
                if ($el['j'] === $min['j'] and $el['i'] === $min['i']) {
                    $current_j = $j; $current_i = $i;
                }

        //print_r($new_plan5);
        //print_r($min);

        // making cycle
        $direction_to = 'down';
        $new_plan5[$current_j][$current_i]['direction_from'] = '';
        $new_plan5[$current_j][$current_i]['continue_cycle'] = '';
        do {
            //echo "$direction_to\n";
            //echo "$current_j $current_i\n";
            //echo '>'.$new_plan5[$current_j][$current_i]['el']."<\n";

            $cycle_order[] = $new_plan5[$current_j][$current_i];

            if ($direction_to === 'down' and !isset($new_plan5[$current_j + 1][$current_i])) {
                while ($new_plan5[$current_j][$current_i]['el'] === ' ') {
                    $current_j--;
                }
                if ($new_plan5[$current_j][$current_i]['direction_from'] === 'left') {
                    $direction_to = 'up';
                } else {
                    $direction_to = 'left';
                }
            }
            elseif ($direction_to === 'left' and !isset($new_plan5[$current_j][$current_i - 1])) {
                while ($new_plan5[$current_j][$current_i]['el'] === ' ') {
                    $current_i++;
                }
                if ($new_plan5[$current_j][$current_i]['direction_from'] === 'up') {
                    $direction_to = 'right';
                } else {
                    $direction_to = 'up';
                }
            }
            elseif ($direction_to === 'up' and !isset($new_plan5[$current_j - 1][$current_i])) {
                while ($new_plan5[$current_j][$current_i]['el'] === ' ') {
                    $current_j++;
                }
                if ($new_plan5[$current_j][$current_i]['direction_from'] === 'right') {
                    $direction_to = 'down';
                } else {
                    $direction_to = 'right';
                }
            }
            elseif ($direction_to === 'right' and !isset($new_plan5[$current_j][$current_i + 1])) {
                while ($new_plan5[$current_j][$current_i]['el'] === ' ') {
                    $current_i--;
                }
                if ($new_plan5[$current_j][$current_i]['direction_from'] === 'down') {
                    $direction_to = 'left';
                } else {
                    $direction_to = 'down';
                }
            }

            if ($direction_to === 'down' and isset($new_plan5[$current_j + 1][$current_i])) {
                $current_j++;
                $new_plan5[$current_j][$current_i]['direction_from'] = 'up';
            }
            elseif ($direction_to === 'left' and isset($new_plan5[$current_j][$current_i - 1])) {
                $current_i--;
                $new_plan5[$current_j][$current_i]['direction_from'] = 'right';
            }
            elseif ($direction_to === 'up' and isset($new_plan5[$current_j - 1][$current_i])) {
                $current_j--;
                $new_plan5[$current_j][$current_i]['direction_from'] = 'down';
            }
            elseif ($direction_to === 'right' and isset($new_plan5[$current_j][$current_i + 1])) {
                $current_i++;
                $new_plan5[$current_j][$current_i]['direction_from'] = 'left';
            }
            //echo "count_cycle=".count($cycle_order)."\n";
        } while (!isset($new_plan5[$current_j][$current_i]['continue_cycle']) or count($cycle_order) < count($new_plan5) * 2);

        // after tree walking elements can duplicate, decide it
        $cycle_order = array_unique($cycle_order, SORT_REGULAR); //print_r($cycle_order);

        // setting +/- in the closed loop
        $sign = '+';
        foreach ($cycle_order as $k => $v) {
            if ($v['el'] !== ' ') {
                $cycle_order[$k]['sign'] = $sign;
                $sign = $sign === '+' ? '-' : '+';
            }
        } //print_r($cycle_order);

        // marking +/- in the base plan
        $new_plan6 = $new_plan4;
        foreach ($cycle_order as $k => $v) {
            if ($v['el'] !== ' ') {
                $new_plan6[$v['j']][$v['i']] = "{$v['el']}\n{$v['sign']}";
                //echo "$k\"{$v['el']}{$v['sign']}\"\n";
            }
        } //print_r($new_plan6);

        // searching min in "-"
        $_min = null;
        foreach ($cycle_order as $k => $v) {
            if ($v['el'] !== ' ')
                if ($v['sign'] === '-') {
                    if ($_min === null) $_min = $v;
                    elseif ($_min['el'] > $v['el']) $_min = $v;
                }
        }
        //echo 'MIN='; print_r($_min);

        // adding min to "+" and subtracting min from "-"
        foreach ($cycle_order as $k => $v) {
            if ($v['el'] !== ' ')
                if ($v['sign'] === '+') $cycle_order[$k]['el'] += $_min['el'];
                else $cycle_order[$k]['el'] -= $_min['el'];
        }

        //print_r($cycle_order);

        // making new plan by recalculation cycle
        $new_plan7 = $new_plan4;
        foreach ($cycle_order as $k => $v) {
            if ($v['el'] !== ' ') $new_plan7[$v['j']][$v['i']] = $v['el'];
            else $new_plan7[$v['j']][$v['i']] = null;
        }
        $new_plan7[$_min['j']][$_min['i']] = null;

        return [
            $new_plan4,
            $new_plan6,
            $new_plan7,
        ];
    }

    public function getFreeCellEstimateMin($matrix): array
    {
        $min['el'] = $matrix[0][0];
        foreach ($matrix as $j => $row)
            foreach ($row as $i => $el)
                if ($el < $min['el']) {
                    $min['el'] = $el; $min['j'] = $j; $min['i'] = $i;
                }
        return $min;
    }

    public function getPlanCost(array $plan): int
    {
        $plan_cost = 0;
        foreach ($plan as $j => $row)
            foreach ($row as $i => $el)
                if ($el !== null) {
                    $plan_cost += $plan[$j][$i] * $this->costs[$j][$i];
                }

        return $plan_cost;
    }

    public function getCostStr(array $plan): string
    {
        $cost_str = '';
        foreach ($plan as $j => $row)
            foreach ($row as $i => $el)
                if ($el !== null)
                    $cost_str .= "{$plan[$j][$i]}*{$this->costs[$j][$i]} + ";

        return substr($cost_str, 0, -3);
    }

    public function checkClosedTask()
    {
        $delta = array_sum($this->storages) - array_sum($this->shops);
        if ($delta < 0) {
            $this->storages[] = -$delta;
            for ($i = 0; $i < $this->shop_count; $i++) $this->costs[$this->storage_count][$i] = 0;
            $this->storage_count++;
        }
        if ($delta > 0) {
            $this->shops[] = $delta;
            for ($i = 0; $i < $this->storage_count; $i++) $this->costs[$i][$this->shop_count] = 0;
            $this->shop_count++;
        }
        return $this;
    }

    public function setOptimalityCriterion(string $oc)
    {
        $this->optimality_criterion = $oc;
        return $this;
    }

    public function getOptimalityCriterion()
    {
        return $this->optimality_criterion;
    }

    public function getCosts()
    {
        return $this->costs;
    }

    public function getStorages()
    {
        return $this->storages;
    }

    public function getShops()
    {
        return $this->shops;
    }

    public function getColorEvaluationMatrix(array $plan, array $evaluation_matrix, int $min): array
    {
        $color_evaluation_matrix = [];
        for ($j = 0; $j < $this->storage_count; $j++) {
            for ($i = 0; $i < $this->shop_count; $i++) {
                if ($plan[$j][$i] === null)
                    $color_evaluation_matrix[$j][$i] =
                        $evaluation_matrix[$j][$i] === $min ? "<bg=red>{$evaluation_matrix[$j][$i]}</>" : "<fg=green>{$evaluation_matrix[$j][$i]}</>";
                else $color_evaluation_matrix[$j][$i] = "{$evaluation_matrix[$j][$i]}";
            }
        }
        return $color_evaluation_matrix;
    }

    public function getNewBasePlan($plan, $new_plan)
    {
        foreach ($new_plan as $j => $row) {
            foreach ($row as $i => $el) {
                $plan[$j][$i] = $el;
            }
        }
        return $plan;
    }

    public function getU($j = null)
    {
        return $j === null ? $this->u : $this->u[$j];
    }

    public function getV($i = null)
    {
        return $i === null ? $this->v : $this->v[$i];
    }

    public function getPlanCount(): int
    {
        return $this->plan_count;
    }

    public function incPlanCount(): self
    {
        $this->plan_count = isset($this->plan_count) ? $this->plan_count + 1 : 1;
        return $this;
    }

    public function getShopCount(): int
    {
        return $this->shop_count;
    }

    public function getVCount(): int
    {
        return count($this->v);
    }

}