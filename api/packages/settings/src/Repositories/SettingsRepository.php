<?php

namespace Ulams\Settings\Repositories;

use Ulams\Core\Repositories\BaseRepository;
use Ulams\Settings\Models\Setting;
use Ulams\Settings\Repositories\Contracts\SettingsRepositoryContract;

class SettingsRepository extends BaseRepository implements SettingsRepositoryContract
{
    /**
     * @var array
     */
    protected $fieldSearchable = [
        'key',
        'group',
    ];

    /**
     * Return searchable fields
     *
     * @return array
     */
    public function getFieldsSearchable()
    {
        return $this->fieldSearchable;
    }

    /**
     * Configure the Model
     **/
    public function model()
    {
        return Setting::class;
    }

    public function findOrCreate(array $data): Setting
    {
        /** @var Setting */
        return $this->model->newQuery()->firstOrCreate([
            'key' => $data['key'],
            'group' => $data['group']
        ], $data);
    }
}
