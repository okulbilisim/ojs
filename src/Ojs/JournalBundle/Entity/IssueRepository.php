<?php

namespace Ojs\JournalBundle\Entity;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NoResultException;

/**
 * IssueRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class IssueRepository extends EntityRepository
{
    /**
     * Return issue count by condition
     * @param $field
     * @param $value
     * @return mixed
     */
    public function getCountBy($field, $value)
    {
        $qb = $this->createQueryBuilder("u");
        $qb->select("count(u.id)")
            ->where(
                $qb->expr()->eq("u.$field", ':value')
            )
            ->setParameter("value", $value);
        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Just get journal's last issue id
     * @param  Journal $journal
     * @return mixed
     */
    public function getLastIssueByJournal(Journal $journal)
    {
        $query = $this->createQueryBuilder("i")
            ->andWhere('i.journal = :journal')
            ->andWhere('i.published = :published')
            ->andWhere('i.year IS NOT NULL')
            ->orderBy('i.year', 'DESC')
            ->orderBy('i.datePublished', 'DESC')
            ->setParameter('journal', $journal)
            ->setParameter('published', true)
            ->setMaxResults(1)
            ->getQuery();
        $issues = $query->getResult();
        if(count($issues) == 0){
            return null;
        }
        if(count($issues) == 1){
            return $issues[0];
        }
        return null;
    }

    public function getIds()
    {
        $query = $this
            ->createQueryBuilder('issue')
            ->select('issue.id')
            ->getQuery();

        return $query->getArrayResult();
    }
    
    public function getIdsByJournal(Journal $journal)
    {
        $query = $this
            ->createQueryBuilder('issue')
            ->select('issue.id')
            ->where('issue.journal = :journal')
            ->setParameter('journal', $journal)
            ->getQuery();

        return $query->getArrayResult();
    }

    /**
     * @param Journal $journal
     * @param bool $withFutureIssues
     * @return array
     */
    public function getByYear(Journal $journal, $withFutureIssues = false)
    {
        $query = $this
            ->createQueryBuilder('issue')
            ->select('issue')
            ->where('issue.journal = :journal')
            ->andWhere('issue.published = true')
            ->setParameter('journal', $journal)
            ->orderBy('issue.datePublished', 'DESC');

        if (!$withFutureIssues) {
            $query->andWhere('issue.inPress = false');
        }

        $query = $query->getQuery();
        $years = [];

        /** @var Issue $issue */
        foreach ($query->getResult() as $issue){
            $years[$issue->getYear()][] = $issue;
        }

        ksort($years);
        return array_reverse($years, true);
    }
}
