<?php

declare(strict_types=1);

class DerbyTest extends DatabaseTestCase
{
    private function result(): array
    {
        return ['place' => 1, 'won' => true, 'bonus' => 6000, 'mult' => 1, 'hits' => 10, 'drift' => 1000, 'wrecks' => 2, 'fatalities' => 1, 'raceLoss' => false];
    }

    public function testRewardsIncludeFiveHundredForTheLifeAndHaveNoTotalCeiling(): void
    {
        $report = $this -> result();
        $this -> assertSame(815, Derby::reward($report));
        $report['hits'] = 2000;
        $this -> assertTrue(Derby::reward($report) > 1000);
        $report['hits'] = 10; $report['drift'] = 1000000;
        $this -> assertSame(859, Derby::reward($report));
    }

    public function testEntryUpgradeAndRewardArePaidExactlyOnce(): void
    {
        $wallet = GameWallet::createGuest(hash('sha256', random_bytes(32)));
        $race = bin2hex(random_bytes(16)); $purchase = bin2hex(random_bytes(16));
        try {
            GameWallet::visit($wallet);
            $entry = Derby::enter($wallet, $race, 'standard');
            $this -> assertSame(500, $entry['wallet']['balance']);
            $this -> assertSame(500, Derby::enter($wallet, $race, 'standard')['wallet']['balance']);
            $upgrade = Derby::purchase($wallet, $purchase, 'car', 'engine', 0);
            $this -> assertSame(300, $upgrade['wallet']['balance']);
            $this -> assertSame(300, Derby::purchase($wallet, $purchase, 'car', 'engine', 0)['wallet']['balance']);
            $this -> assertSame(1, Derby::career($wallet)['car']['engine']);
            $entry['round']['startedAt'] -= 60;
            DB::run('UPDATE `GamePlays` SET `result` = ? WHERE `requestKey` = ?', 'ss', json_encode($entry['round']), $race);
            $settled = Derby::finish($wallet, $race, $this -> result());
            $this -> assertSame(1115, $settled['wallet']['balance']);
            $this -> assertSame(1115, Derby::finish($wallet, $race, $this -> result())['wallet']['balance']);
            $report = $this -> result(); $report['hits']++;
            $rejected = false;
            try { Derby::finish($wallet, $race, $report); } catch (\DomainException) { $rejected = true; }
            $this -> assertTrue($rejected);
        } finally { DB::run('DELETE FROM `GameWallets` WHERE `walletId` = ?', 'i', $wallet); }
    }

    public function testUnpaidAndAnotherWalletsRaceCannotAwardChips(): void
    {
        $one = GameWallet::createGuest(hash('sha256', random_bytes(32)));
        $two = GameWallet::createGuest(hash('sha256', random_bytes(32)));
        try {
            GameWallet::visit($one); GameWallet::visit($two);
            $race = bin2hex(random_bytes(16)); Derby::enter($one, $race, 'standard');
            foreach ([$race, bin2hex(random_bytes(16))] as $key) {
                $rejected = false;
                try { Derby::finish($two, $key, $this -> result()); } catch (\DomainException) { $rejected = true; }
                $this -> assertTrue($rejected);
            }
            $this -> assertSame(1000, GameWallet::visit($two)['balance']);
        } finally { DB::run('DELETE FROM `GameWallets` WHERE `walletId` IN (?, ?)', 'ii', $one, $two); }
    }

    public function testGuestUpgradeReceiptsSurviveAccountClaim(): void
    {
        $user = self::createUser();
        $guest = GameWallet::createGuest(hash('sha256', random_bytes(32)));
        try {
            $account = GameWallet::forUser($user); GameWallet::visit($guest);
            Derby::purchase($guest, bin2hex(random_bytes(16)), 'buggy', 'grip', 0);
            GameWallet::merge($guest, $account);
            $this -> assertSame(1, Derby::career($account)['buggy']['grip']);
            $this -> assertSame(800, GameWallet::visit($account)['balance']);
        } finally {
            DB::run('DELETE FROM `GameWallets` WHERE `walletId` = ?', 'i', $guest);
            DB::run('DELETE FROM `Users` WHERE `userId` = ?', 'i', $user);
        }
    }
}
