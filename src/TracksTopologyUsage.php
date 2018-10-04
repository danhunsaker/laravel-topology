<?php

namespace DanHunsaker\PasswordTopology;

use DanHunsaker\PasswordTopology\Topology;
use DB;

trait TracksTopologyUsage
{
    protected $topologyTable = null;

    /**
     * Update password topology usage data, if the audit store is configured.
     *
     * @param  string $password
     * @return void
     */
    protected function updateTopologyUsage($password)
    {
        if (!is_null($store = config('topology.audit_store'))) {
            $broker = config('auth.defaults.passwords');
            $provider = config("auth.passwords.{$broker}.provider");
            $userClass = config("auth.providers.{$provider}.model");
            $userTable = with(new $userClass)->table;

            $topology = Topology::convert($password);
            $db = DB::connection($store);

            $db->transaction(function () use ($topology, $db) {
                $query = $db->table('topologies')
                            ->where('table', $this->topologyTable ?: $userTable)
                            ->where('topology', $topology)
                            ->lockForUpdate();

                if ($query->exists()) {
                    $qeury->update(['count' => ($query->value('count') ?: 0) + 1]);
                } else {
                    $db->table('topologies')->insert([
                        'table' => $this->topologyTable ?: $userTable,
                        'topology' => $topology,
                        'count' => 1,
                    ]);
                }
            });
        }
    }
}
