<?php

namespace Tests\Feature\App;

use App\Models\Person;
use App\Models\PhoneNumber;
use App\Models\User;
use App\Services\DossierService;

class DirectoryApiTest extends AppApiTestCase
{
    /**
     * @return array{person: Person, phone: PhoneNumber, desk: PhoneNumber}
     */
    private function dossier(User $user): array
    {
        $target = $this->targetOf($user);
        $dossier = app(DossierService::class);

        $person = $dossier->upsertPerson($target, $user, [
            'key' => 'jane-doe', 'name' => 'Jane Doe', 'title' => 'CEO', 'department' => 'Executive',
            'source_url' => 'https://acme.example/about',
        ], 'test')['person'];

        $phone = $dossier->upsertPhone($target, $user, [
            'number' => '514-555-0100', 'label' => 'Reception', 'person_key' => 'jane-doe',
            'source_url' => 'https://acme.example/contact',
        ], 'test')['phone'];

        $desk = $dossier->upsertPhone($target, $user, [
            'number' => '514-555-0199', 'extension' => '12', 'label' => 'Help desk',
            'source_url' => 'https://acme.example/contact',
        ], 'test')['phone'];

        $dossier->upsertPretext($target, $user, [
            'key' => 'it-callback', 'title' => 'IT callback', 'persona' => 'New helpdesk tech',
            'flag_ids' => ['05-03'], 'phone_numbers' => ['+1 514 555 0199', '514-555-0000'], 'confidence' => 0.6,
        ], 'test');

        return ['person' => $person, 'phone' => $phone, 'desk' => $desk];
    }

    public function test_people_are_listed_with_source_and_phone_ids(): void
    {
        $user = $this->contestant();
        $target = $this->targetOf($user);
        ['person' => $person, 'phone' => $phone] = $this->dossier($user);

        $this->actingAs($user)->getJson(route('app.targets.people', $target->id))
            ->assertOk()
            ->assertJsonPath('target_id', $target->id)
            ->assertJsonCount(1, 'people')
            ->assertJsonPath('people.0.id', $person->id)
            ->assertJsonPath('people.0.key', 'jane-doe')
            ->assertJsonPath('people.0.title', 'CEO')
            ->assertJsonPath('people.0.source.url', 'https://acme.example/about')
            ->assertJsonPath('people.0.phone_ids', [$phone->id])
            ->assertJsonStructure(['people' => [['id', 'key', 'name', 'title', 'department', 'tenure', 'profile_url', 'notes', 'source' => ['url', 'title', 'retrieved_at'], 'phone_ids', 'updated_at']]]);
    }

    public function test_phones_are_listed_by_label_with_person_and_source(): void
    {
        $user = $this->contestant();
        $target = $this->targetOf($user);
        ['person' => $person, 'phone' => $phone] = $this->dossier($user);

        $this->actingAs($user)->getJson(route('app.targets.phones', $target->id))
            ->assertOk()
            ->assertJsonPath('target_id', $target->id)
            ->assertJsonCount(2, 'phones')
            ->assertJsonPath('phones.0.label', 'Help desk')
            ->assertJsonPath('phones.0.extension', '12')
            ->assertJsonPath('phones.0.person', null)
            ->assertJsonPath('phones.1.id', $phone->id)
            ->assertJsonPath('phones.1.number', '+15145550100')
            ->assertJsonPath('phones.1.person', ['id' => $person->id, 'key' => 'jane-doe', 'name' => 'Jane Doe'])
            ->assertJsonPath('phones.1.ring_verified_at', null)
            ->assertJsonPath('phones.1.source.url', 'https://acme.example/contact')
            ->assertJsonStructure(['phones' => [['id', 'number', 'extension', 'label', 'department', 'person', 'ring_verified_at', 'notes', 'source', 'updated_at']]]);
    }

    public function test_ring_verified_stamps_the_phone_and_records_activity(): void
    {
        $user = $this->contestant();
        $target = $this->targetOf($user);
        ['phone' => $phone] = $this->dossier($user);

        $this->actingAs($user)->postJson(route('app.targets.phones.ring-verified', [$target->id, $phone->id]))
            ->assertOk()
            ->assertJsonPath('target_id', $target->id)
            ->assertJsonPath('phone.id', $phone->id)
            ->assertJsonPath('phone.person.key', 'jane-doe')
            ->assertJsonPath('phone.source.url', 'https://acme.example/contact');

        $this->assertNotNull($phone->refresh()->ring_verified_at);
        $this->assertDatabaseHas('activities', [
            'target_id' => $target->id, 'kind' => 'phone.verified', 'subject_type' => 'PhoneNumber', 'subject_id' => $phone->id, 'via' => 'web',
        ]);
    }

    public function test_ring_verified_404s_on_a_phone_from_another_target(): void
    {
        $alice = $this->contestant('Alice Corp');
        ['phone' => $alicePhone] = $this->dossier($alice);
        $bob = $this->contestant('Bob Ltd');

        $this->actingAs($bob)->postJson(route('app.targets.phones.ring-verified', [$this->targetOf($bob)->id, $alicePhone->id]))
            ->assertNotFound();

        $this->assertNull($alicePhone->refresh()->ring_verified_at);
    }

    public function test_pretexts_resolve_their_phone_numbers_and_creator(): void
    {
        $user = $this->contestant();
        $target = $this->targetOf($user);
        ['desk' => $desk] = $this->dossier($user);

        $this->actingAs($user)->getJson(route('app.targets.pretexts', $target->id))
            ->assertOk()
            ->assertJsonPath('target_id', $target->id)
            ->assertJsonCount(1, 'pretexts')
            ->assertJsonPath('pretexts.0.key', 'it-callback')
            ->assertJsonPath('pretexts.0.flag_ids', ['05-03'])
            ->assertJsonPath('pretexts.0.phone_numbers', [['id' => $desk->id, 'number' => '+15145550199', 'extension' => '12', 'label' => 'Help desk']])
            ->assertJsonPath('pretexts.0.confidence', 0.6)
            ->assertJsonPath('pretexts.0.by', $user->name)
            ->assertJsonStructure(['pretexts' => [['id', 'key', 'title', 'persona', 'script', 'flag_ids', 'phone_numbers', 'confidence', 'by', 'updated_at']]]);
    }
}
