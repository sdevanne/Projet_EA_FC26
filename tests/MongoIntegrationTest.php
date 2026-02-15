<?php
declare(strict_types=1);

namespace Projet\Fc25\Tests;

use PHPUnit\Framework\TestCase;
use Projet\Fc25\ConnexionMongo;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

final class MongoIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // reset connexion (utile si env a changé)
        ConnexionMongo::reset();

        $db = ConnexionMongo::db();

        // Nettoyage base de tests
        foreach (['leagues','teams','players','scout_reports'] as $c) {
            try { $db->dropCollection($c); } catch (\Throwable $e) {}
        }

        // Index utiles (comme prod)
        try { $db->leagues->createIndex(['code' => 1], ['unique' => true]); } catch (\Throwable $e) {}
        try { $db->teams->createIndex(['leagueId' => 1, 'slug' => 1], ['unique' => true]); } catch (\Throwable $e) {}
        try { $db->players->createIndex(['teamId' => 1, 'slug' => 1], ['unique' => true]); } catch (\Throwable $e) {}
    }

    public function testInsertLeagueTeamPlayerAndScoutReport(): void
    {
        $db = ConnexionMongo::db();

        // 1) League
        $league = [
            'code' => 'TST1',
            'name' => 'Test League',
            'country' => 'Testland',
            'level' => 1,
            'createdAt' => new UTCDateTime(),
        ];
        $db->leagues->insertOne($league);
        $lg = $db->leagues->findOne(['code' => 'TST1']);
        $this->assertNotNull($lg);
        $this->assertSame('Test League', (string)$lg['name']);

        // 2) Team lié à la league
        $team = [
            'name' => 'Test FC',
            'slug' => 'test-fc',
            'leagueId' => $lg['_id'],
            'rating' => 80,
            'budget' => 1000000,
            'createdAt' => new UTCDateTime(),
        ];
        $db->teams->insertOne($team);
        $tm = $db->teams->findOne(['leagueId' => $lg['_id'], 'slug' => 'test-fc']);
        $this->assertNotNull($tm);
        $this->assertSame('Test FC', (string)$tm['name']);

        // 3) Player lié à la team + league
        $player = [
            'leagueId' => $lg['_id'],
            'teamId' => $tm['_id'],
            'team' => $tm['name'],
            'playerName' => 'John Doe',
            'slug' => 'john-doe',
            'positions' => 'ST',
            'overall' => 75,
            'age' => 22,
            'contractStart' => 1739577600000,
            'contractEnd' => 1771113600000,
            'marketValue' => 123456,
            'createdAt' => new UTCDateTime(),
            'updatedAt' => new UTCDateTime(),
        ];
        $db->players->insertOne($player);
        $pl = $db->players->findOne(['teamId' => $tm['_id'], 'slug' => 'john-doe']);
        $this->assertNotNull($pl);
        $this->assertSame('John Doe', (string)$pl['playerName']);

        // 4) Scout report lié au player
        $report = [
            'playerId' => $pl['_id'],
            'rating' => 8,
            'strengths' => ['Vitesse'],
            'weaknesses' => ['Défense'],
            'notes' => 'Bon potentiel',
            'createdAt' => new UTCDateTime(),
        ];
        $db->scout_reports->insertOne($report);
        $rp = $db->scout_reports->findOne(['playerId' => $pl['_id']]);
        $this->assertNotNull($rp);
        $this->assertSame(8, (int)$rp['rating']);
        $this->assertSame('Bon potentiel', (string)$rp['notes']);
    }

    public function testDeletePlayerThenReportsStillExistUnlessYouHandleCascade(): void
    {
        $db = ConnexionMongo::db();

        // Insert minimal player + report
        $pid = new ObjectId();
        $db->players->insertOne(['_id' => $pid, 'playerName' => 'X', 'slug'=>'x', 'teamId'=>new ObjectId()]);
        $db->scout_reports->insertOne(['playerId' => $pid, 'rating' => 7, 'createdAt' => new UTCDateTime()]);

        $this->assertSame(1, $db->scout_reports->countDocuments(['playerId' => $pid]));

        // delete player
        $db->players->deleteOne(['_id' => $pid]);

        // report reste (comportement Mongo normal) => test documente le comportement
        $this->assertSame(1, $db->scout_reports->countDocuments(['playerId' => $pid]));
    }
}
