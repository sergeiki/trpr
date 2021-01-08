<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use sergeiki\trpr;

class TransportationProblemCommand extends Command
{
    protected static $defaultName = 'app:transportation-problem';

    protected function configure()
    {
        $this
            ->setDescription('Decisions a transportation problem')
            ->addArgument('arg1', InputArgument::OPTIONAL, 'The input data csv-file with  a tab delimiter')
            ->addOption('oc', 'o', InputOption::VALUE_REQUIRED, 'Optimality criterion: min or max', 'min')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $arg1 = $input->getArgument('arg1');
        $option1 = $input->getOption('oc');

        if ($arg1) {
            $shops = $storages = $costs = [];
            if (file_exists("data/$arg1")) {
                if (($handle = fopen("data/$arg1", "r")) !== FALSE) {
                    $shops = array_map('intval', fgetcsv($handle, 0, "\t"));
                    array_shift($shops);
                    while (($data = fgetcsv($handle, 0, "\t")) !== FALSE) {
                        $data = array_map('intval', $data);
                        $storages[] = array_shift($data);
                        $costs[] = $data;
                    }
                    fclose($handle);
                }
            } else {
                $io->caution("The input data txt-file '$arg1' is NOT exist!");
                return Command::FAILURE;
            }
        } else {
            // default shops
            $shops    = [200, 90, 180, 20, 110];
            $storages = [50, 150, 70, 30, 250, 75];

            // default costs [storage] = [shops]
            $costs[0] = [2, 14, 3,  2,  1];
            $costs[1] = [3,  5, 3, 12,  9];
            $costs[2] = [4,  1, 3,  4,  4];
            $costs[3] = [7, 11, 3,  2,  5];
            $costs[4] = [2,  8, 3,  2,  4];
            $costs[5] = [6,  4, 3,  5,  1];
        }


        $tp = (new TransportationProblem($storages, $shops, $costs))
            ->checkClosedTask()
            ->setOptimalityCriterion($option1)
        ;

        $plan = $tp->incPlanCount()->getBasePlan();

        $table = []; foreach ($tp->getStorages() as $j => $el) $table[] = array_merge([$el], $plan[$j], ["A$j"]);
        $title = 'Base Plan '.$tp->getPlanCount();
        $headers = array_merge([''], $tp->getShops());
        $footers = ['']; for ($i = 0; $i < $tp->getShopCount(); $i++) $footers[] = "B$i";
        $io->newLine(); (new Table($io))->setHeaderTitle($title)->setHeaders($headers)->setRows($table)->addRow(new TableSeparator())->addRow($footers)->render();



        $table = []; foreach ($tp->getStorages() as $j => $el) $table[] = array_merge([$el], $tp->getCosts()[$j], ["A$j"]);
        $title = 'Cost Table (Cji)';
        //$headers = array_merge([''], $tp->getShops());
        //$footers = ['']; for ($i = 0; $i < $tp->getShopCount(); $i++) $footers[] = "B$i";
        $io->newLine(); (new Table($io))->setHeaderTitle($title)->setHeaders($headers)->setRows($table)->addRow(new TableSeparator())->addRow($footers)->render();

        $evaluation_matrix = $tp->usePotentialMethod($plan);
        $FCEMin = $tp->getFreeCellEstimateMin($evaluation_matrix);
        $color_evaluation_matrix = $tp->getColorEvaluationMatrix($plan, $evaluation_matrix, $FCEMin['el']);

        $table = []; foreach ($tp->getStorages() as $j => $el) $table[] = array_merge([$tp->getU($j)], $color_evaluation_matrix[$j], ["U$j"]);
        $title = 'Evaluation Matrix (Dji) '.$tp->getPlanCount();
        $headers = $footers = ['']; for ($i = 0; $i < $tp->getVCount(); $i++) { $headers[] = $tp->getV($i); $footers[] = "V$i"; }
        $io->newLine(); (new Table($io))->setHeaderTitle($title)->setHeaders($headers)->setRows($table)->addRow(new TableSeparator())->addRow($footers)->render();

        $plan_cost = $tp->getPlanCost($plan);
        $cost_str = $tp->getCostStr($plan);
        $io->newline(); $io->text("Total cost of transportation: $cost_str = $plan_cost");


        while ($FCEMin['el'] < 0) {
            $io->error("This Plan is NOT optimal!");
            if ($tp->getOptimalityCriterion() === 'min') $io->warning("Optimality criterion for min: Dji = Cji - C'ji > 0, where C'ji = Uj + Vi; Dji = {$FCEMin['el']} < 0");
            if ($tp->getOptimalityCriterion() === 'max') $io->warning("Optimality criterion for max: Dji = C'ji - Cji > 0, where C'ji = Uj + Vi; Dji = {$FCEMin['el']} < 0");

            $io->newLine(2); $io->section("N E W    R E C A L C U L A T I O N    C Y C L E");

            $new_plans = $tp->getRecalculationCycle($plan, $FCEMin);

            foreach ($new_plans as $new_plan) {
                $table = []; foreach ($new_plan as $j => $el) $table[] = array_merge([$tp->getU($j)], $new_plan[$j], ["U$j"]);
                $title = 'Recalculation Cycle '.$tp->getPlanCount();
                $headers = $footers = ['']; for ($i = 0; $i < count($new_plan); $i++) { $headers[] = $tp->getV($i); $footers[] = "V$i"; }
                $io->newLine(); (new Table($io))->setHeaderTitle($title)->setHeaders($headers)->setRows($table)->addRow(new TableSeparator())->addRow($footers)->render();
            }

            $new_base_plan = $tp->incPlanCount()->getNewBasePlan($plan, $new_plan);

            $table = []; foreach ($tp->getStorages() as $j => $el) $table[] = array_merge([$el], $new_base_plan[$j], ["A$j"]);
            $title = 'Base Plan '.$tp->getPlanCount();
            $headers = array_merge([''], $tp->getShops());
            $footers = ['']; for ($i = 0; $i < $tp->getShopCount(); $i++) $footers[] = "B$i";
            $io->newLine(); (new Table($io))->setHeaderTitle($title)->setHeaders($headers)->setRows($table)->addRow(new TableSeparator())->addRow($footers)->render();

            $table = []; foreach ($tp->getStorages() as $j => $el) $table[] = array_merge([$el], $tp->getCosts()[$j], ["A$j"]);
            $title = 'Cost Table (Cji)';
            //$headers = array_merge([''], $tp->getShops());
            //$footers = ['']; for ($i = 0; $i < $tp->getShopCount(); $i++) $footers[] = "B$i";
            $io->newLine(); (new Table($io))->setHeaderTitle($title)->setHeaders($headers)->setRows($table)->addRow(new TableSeparator())->addRow($footers)->render();

            $evaluation_matrix = $tp->usePotentialMethod($new_base_plan);
            $FCEMin = $tp->getFreeCellEstimateMin($evaluation_matrix);
            $color_evaluation_matrix = $tp->getColorEvaluationMatrix($new_base_plan, $evaluation_matrix, $FCEMin['el']);

            $table = []; foreach ($tp->getStorages() as $j => $el) $table[] = array_merge([$tp->getU($j)], $color_evaluation_matrix[$j], ["U$j"]);
            $title = 'Evaluation Matrix (Dji) '.$tp->getPlanCount();
            $headers = $footers = ['']; for ($i = 0; $i < $tp->getVCount(); $i++) { $headers[] = $tp->getV($i); $footers[] = "V$i"; }
            $io->newLine(); (new Table($io))->setHeaderTitle($title)->setHeaders($headers)->setRows($table)->addRow(new TableSeparator())->addRow($footers)->render();

            $plan_cost = $tp->getPlanCost($new_base_plan); $cost_str = $tp->getCostStr($new_base_plan);
            $io->newline(); $io->text("Total cost of transportation: $cost_str = $plan_cost");

            $plan = $new_base_plan;
        }


        $io->success("This Plan is optimal!");
        if ($tp->getOptimalityCriterion() === 'min') $io->success("Optimality criterion for min: Dji = Cji - C'ji > 0, where C'ji = Uj + Vi");
        if ($tp->getOptimalityCriterion() === 'max') $io->success("Optimality criterion for max: Dji = C'ji - Cji > 0, where C'ji = Uj + Vi");

        if ($arg1) $io->note(sprintf('Used an input data file: %s', $arg1));
        if ($option1) $io->note(sprintf('Used an optimality criterion option: %s', $option1));


        return Command::SUCCESS;
    }
}
