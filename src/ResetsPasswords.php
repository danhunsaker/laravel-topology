<?php

namespace DanHunsaker\PasswordTopology;

use Illuminate\Foundation\Auth\ResetsPasswords as Base;

trait ResetsPasswords {
    use Base, TracksTopologyUsage;

    /**
     * {@inherit}
     */
    protected function resetPassword($user, $password)
    {
        parent::resetPassword($user, $password);

        $this->updateTopologyUsage($password);
    }

    /**
     * {@inherit}
     */
    protected function rules()
    {
        $rules = parent::rules();
        $rules['password'] .= '|topology';

        return $rules;
    }
}
