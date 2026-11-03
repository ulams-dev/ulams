<?php

namespace Ulams\Reporting\Query;

use Illuminate\Support\Facades\DB;

/**
 * Small-group suppression for a grouped report on a dataset that holds learners (ADR 0194, plan 6.7).
 *
 * A group with fewer distinct learners than the threshold is withheld: its figures never leave the server. Withholding
 * alone is not enough, because a viewer can ask for the grand total and subtract the visible groups to get the withheld
 * one, so the withheld groups together must also span at least the threshold in distinct learners. When they do not
 * (a single withheld group is the common case), the smallest visible group is withheld with them (complementary
 * suppression). Learners can be in several groups, so "together" is counted on the learners themselves, in SQL, not
 * summed from the per-group counts.
 *
 * What this does not do: stop a viewer who may run row-level reports from reading individuals, and stop two different
 * filtered queries from being subtracted. Permission to the dataset is what decides who sees learner data at all.
 */
class Suppression
{
    /**
     * @param list<array<string,mixed>> $rows the groups, each with the distinct learners in `QueryCompiler::SUBJECTS`
     * @return array{0:list<array<string,mixed>>,1:int} the groups that may be shown (order kept) and how many were withheld
     */
    public function apply(CompiledQuery $compiled, array $rows, int $min): array
    {
        $small = [];
        foreach ($rows as $i => $row) {
            if ((int) $row[QueryCompiler::SUBJECTS] < $min) {
                $small[] = $i;
            }
        }
        if ($small === []) {
            return [$rows, 0];
        }

        $together = count($small) === 1 ? (int) $rows[$small[0]][QueryCompiler::SUBJECTS] : $this->learnersTogether($compiled, $min);
        $withheld = array_flip($small);
        if ($together < $min) {
            $visible = array_diff_key($rows, $withheld);
            if ($visible !== []) {
                uasort($visible, fn (array $a, array $b) => (int) $a[QueryCompiler::SUBJECTS] <=> (int) $b[QueryCompiler::SUBJECTS]);
                $withheld[array_key_first($visible)] = true;
            }
        }

        return [array_values(array_diff_key($rows, $withheld)), count($withheld)];
    }

    /** Distinct learners across every group that is below the threshold. */
    private function learnersTogether(CompiledQuery $compiled, int $min): int
    {
        $detail = $compiled->detail;
        if ($detail === null) {
            return 0;
        }
        $names = array_map(fn (int $i) => '"g' . $i . '"', array_keys($compiled->groupNames));
        $sql = $detail->toSql();
        $bindings = $detail->getBindings();

        $match = implode(' and ', array_map(fn (string $n) => "d.{$n} is not distinct from w.{$n}", $names));
        $statement = 'select count(distinct d."__s") as n from (' . $sql . ') as d where exists ('
            . 'select 1 from (select ' . implode(', ', $names) . ' from (' . $sql . ') as x group by ' . implode(', ', $names) . ' having count(distinct x."__s") < ?) as w where ' . $match . ')';

        return (int) (DB::selectOne($statement, [...$bindings, ...$bindings, $min])->n ?? 0);
    }
}
