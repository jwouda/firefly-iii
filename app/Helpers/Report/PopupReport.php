<?php
/**
 * PopupReport.php
 * Copyright (c) 2017 thegrumpydictator@gmail.com
 *
 * This file is part of Firefly III.
 *
 * Firefly III is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Firefly III is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Firefly III. If not, see <http://www.gnu.org/licenses/>.
 */
declare(strict_types=1);

namespace FireflyIII\Helpers\Report;

use FireflyIII\Helpers\Collector\GroupCollectorInterface;
use FireflyIII\Models\Account;
use FireflyIII\Models\Budget;
use FireflyIII\Models\Category;
use FireflyIII\Models\TransactionType;
use FireflyIII\Repositories\Currency\CurrencyRepositoryInterface;
use FireflyIII\Repositories\Journal\JournalRepositoryInterface;
use Illuminate\Support\Collection;
use Log;

/**
 * Class PopupReport.
 *
 * @codeCoverageIgnore
 */
class PopupReport implements PopupReportInterface
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        if ('testing' === config('app.env')) {
            Log::warning(sprintf('%s should not be instantiated in the TEST environment!', get_class($this)));
        }
    }

    /**
     * Collect the transactions for one account and one budget.
     *
     * @param Budget $budget
     * @param Account $account
     * @param array $attributes
     *
     * @return array
     */
    public function balanceForBudget(Budget $budget, Account $account, array $attributes): array
    {
        /** @var GroupCollectorInterface $collector */
        $collector = app(GroupCollectorInterface::class);
        $collector->setAccounts(new Collection([$account]))->setRange($attributes['startDate'], $attributes['endDate'])->setBudget($budget);

        return $collector->getExtractedJournals();
    }

    /**
     * Collect the transactions for one account and no budget.
     *
     * @param Account $account
     * @param array $attributes
     *
     * @return array
     */
    public function balanceForNoBudget(Account $account, array $attributes): array
    {
        /** @var GroupCollectorInterface $collector */
        $collector = app(GroupCollectorInterface::class);
        $collector
            ->setAccounts(new Collection([$account]))
            ->setTypes([TransactionType::WITHDRAWAL])
            ->setRange($attributes['startDate'], $attributes['endDate'])
            ->withoutBudget();

        return $collector->getExtractedJournals();
    }

    /**
     * Collect the transactions for a budget.
     *
     * @param Budget $budget
     * @param array $attributes
     *
     * @return array
     */
    public function byBudget(Budget $budget, array $attributes): array
    {
        // filter by currency, if set.
        $currencyId = $attributes['currencyId'] ?? null;
        $currency   = null;
        if (null !== $currencyId) {
            /** @var CurrencyRepositoryInterface $repos */
            $repos    = app(CurrencyRepositoryInterface::class);
            $currency = $repos->find((int)$currencyId);
        }


        /** @var GroupCollectorInterface $collector */
        $collector = app(GroupCollectorInterface::class);
        $collector->setAccounts($attributes['accounts'])->setRange($attributes['startDate'], $attributes['endDate']);

        if (null !== $currency) {
            $collector->setCurrency($currency);
        }

        if (null === $budget->id) {
            $collector->setTypes([TransactionType::WITHDRAWAL])->withoutBudget();
        }
        if (null !== $budget->id) {
            $collector->setBudget($budget);
        }

        return $collector->getExtractedJournals();
    }

    /**
     * Collect journals by a category.
     *
     * @param Category $category
     * @param array $attributes
     *
     * @return array
     */
    public function byCategory(Category $category, array $attributes): array
    {
        // filter by currency, if set.
        $currencyId = $attributes['currencyId'] ?? null;
        $currency   = null;
        if (null !== $currencyId) {
            /** @var CurrencyRepositoryInterface $repos */
            $repos    = app(CurrencyRepositoryInterface::class);
            $currency = $repos->find((int)$currencyId);
        }

        /** @var GroupCollectorInterface $collector */
        $collector = app(GroupCollectorInterface::class);

        $collector->setAccounts($attributes['accounts'])
                  ->setTypes([TransactionType::WITHDRAWAL, TransactionType::TRANSFER, TransactionType::DEPOSIT])
                  ->setRange($attributes['startDate'], $attributes['endDate'])->withAccountInformation()
                  ->setCategory($category);
        if (null !== $currency) {
            $collector->setCurrency($currency);
        }

        return $collector->getExtractedJournals();
    }

    /**
     * Group transactions by expense.
     *
     * @param Account $account
     * @param array $attributes
     *
     * @return array
     */
    public function byExpenses(Account $account, array $attributes): array
    {
        // filter by currency, if set.
        $currencyId = $attributes['currencyId'] ?? null;
        $currency   = null;
        if (null !== $currencyId) {
            /** @var CurrencyRepositoryInterface $repos */
            $repos    = app(CurrencyRepositoryInterface::class);
            $currency = $repos->find((int)$currencyId);
        }

        /** @var JournalRepositoryInterface $repository */
        $repository = app(JournalRepositoryInterface::class);
        $repository->setUser($account->user);

        /** @var GroupCollectorInterface $collector */
        $collector = app(GroupCollectorInterface::class);

        $collector->setAccounts(new Collection([$account]))
                  ->setRange($attributes['startDate'], $attributes['endDate'])
                  ->setTypes([TransactionType::WITHDRAWAL, TransactionType::TRANSFER]);

        if (null !== $currency) {
            $collector->setCurrency($currency);
        }

        return $collector->getExtractedJournals();
    }

    /**
     * Collect transactions by income.
     *
     * @param Account $account
     * @param array $attributes
     *
     * @return array
     */
    public function byIncome(Account $account, array $attributes): array
    {
        /** @var JournalRepositoryInterface $repository */
        $repository = app(JournalRepositoryInterface::class);
        $repository->setUser($account->user);
        /** @var GroupCollectorInterface $collector */
        $collector = app(GroupCollectorInterface::class);
        $collector
            ->setSourceAccounts(new Collection([$account]))
            ->setDestinationAccounts($attributes['accounts'])
            ->setRange($attributes['startDate'], $attributes['endDate'])
            ->setTypes([TransactionType::DEPOSIT, TransactionType::TRANSFER])
            ->withAccountInformation();
        return $collector->getExtractedJournals();
    }
}
