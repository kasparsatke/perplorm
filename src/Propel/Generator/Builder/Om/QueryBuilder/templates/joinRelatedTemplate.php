
    /**
     * Adds a JOIN clause to the query using the <?= $relationName ?> relation
     *
     * @param string|null $relationAlias Optional alias for the relation
     * @param string|null $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return $this
     */
    public function join<?= $relationName ?>(?string $relationAlias = null, ?string $joinType = <?= $joinType ?>)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('<?= $relationName ?>');

        $join = new ModelJoin();
        $join->setJoinType($joinType);
        $leftAlias = $this->useAliasInSQL ? $this->getModelAlias() : null;
        $join->setupJoinCondition($this, $relationMap, $leftAlias, $relationAlias);
        $previousJoin = $this->getPreviousJoin();
        if ($previousJoin instanceof ModelJoin) {
            $join->setPreviousJoin($previousJoin);
        }

        if ($relationAlias) {
            $this->addAlias($relationAlias, $relationMap->getRightTable()->getName());
        }

        return $this->addJoinObject($join, $relationAlias ?: '<?= $relationName ?>');
    }
