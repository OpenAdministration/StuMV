<?php

namespace App\Http\Livewire\Committee;

use App\Models\Committee;
use Livewire\Component;

class Detail extends Component {

    public Committee $committee;

    public function mount($id) {
        $this->committee = Committee::findOrFail($id);
    }

    public function render() {
        return view(
            'livewire.committee.detail', [
                'committee' => $this->committee
            ]
        )->layout('layouts.app', [
            'headline' => __('committees.details_heading', [
                'name' => $this->committee->name,
                'realm' => $this->committee->realm_uid
            ])
        ]);
    }

}
