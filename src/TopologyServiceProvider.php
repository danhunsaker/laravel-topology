<?php

namespace DanHunsaker\PasswordTopology;

use DanHunsaker\PasswordTopology\Topology;
use Illuminate\Support\ServiceProvider;
use Validator;
use DB;

class TopologyServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'topology');

        if (!is_null($store = config('topology.audit_store'))) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations', 'topology');
        }

        $this->publishes([
            __DIR__.'/../lang' => resource_path('lang/vendor/topology'),
            __DIR__.'/../config/topology.php' => config_path('topology.php'),
        ], 'topology');

        $this->loadConfiguration();
        $this->setupValidators();
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/topology.php', 'topology');
    }

    protected function loadConfiguration()
    {
        Topology::unicode(config('topology.unicode'));
        Topology::allowMany(config('topology.allowed'));
        Topology::forbidMany(config('topology.forbidden'));

        $broker = config('auth.defaults.passwords');
        $provider = config("auth.passwords.{$broker}.provider");
        $userClass = config("auth.providers.{$provider}.model");
        $userTable = with(new $userClass)->table;

        if (!is_null($store = config('topology.audit_store')) &&
            config('topology.max_topo_use') > 0) {
            $topoList = DB::connection($store)
                            ->table('topologies')
                            ->where('table', $userTable)
                            ->where('count', '>', config('topology.max_topo_use'))
                            ->pluck('topology');

            Topology::forbidMany($topoList->all());
        }
    }

    protected function setupValidators()
    {
        Validator::extend('topology', function ($attribute, $value, $parameters, $validator) {
            if (empty($parameters)) {
                // false for forbidden topologies; true otherwise
                return Topology::check($value);
            } else {
                list($forbidden, $allowed) = collect($parameters)->partition(function ($i) {
                    return (config('topology.unicode') ? mb_substr($i, 0, 1) : substr($i, 0, 1)) == '!';
                });
                $converted = Topology::convert($value);

                if ($forbidden->isNotEmpty() && $forbidden->contains("!{$converted}")) {
                    return false;
                } elseif ($allowed->isNotEmpty() && !$allowed->contains("{$converted}")) {
                    return false;
                }

                return true;
            }
        }, trans('topology::validation.topology'));

        Validator::extend('topo-dist', function ($attribute, $value, $parameters, $validator) {
            if (count($parameters) != 1) {
                throw new InvalidArgumentException("Validation rule topo-dist requires exactly 1 parameter.");
            }

            $new = Topology::convert($value);
            $old = Topology::convert(array_get($validator->attributes(), $parameters[0]));

            return levenshtein($old, $new) >= config('topology.min_lev_dist');
        }, trans('topology::validation.distance'));
    }
}
