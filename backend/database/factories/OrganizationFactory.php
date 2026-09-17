<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        return [
            'name' => 'ООО «'.$this->faker->company.'»',
            'full_name' => 'Общество с ограниченной ответственностью «'.$this->faker->company.'»',
            'inn' => (string) $this->faker->numerify('##########'),
            'kpp' => (string) $this->faker->numerify('#########'),
            'ogrn' => (string) $this->faker->numerify('#############'),
            'legal_address' => $this->faker->address,
            'phone' => '+7 (999) 000-00-00',
            'email' => $this->faker->safeEmail,
            'bank_name' => 'ПАО «Банк»',
            'bank_account' => (string) $this->faker->numerify(str_repeat('#', 20)),
            'bank_corr_account' => (string) $this->faker->numerify(str_repeat('#', 20)),
            'bank_bik' => (string) $this->faker->numerify('#########'),
            'director_name' => $this->faker->name,
            'director_position' => 'Генеральный директор',
        ];
    }
}
