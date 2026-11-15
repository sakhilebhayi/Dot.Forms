<x-app-layout>
    {{-- R-52: the book on the Carbon Book's own page scaffold. The old Settings / Forms tab strip and white card were
         Jetstream-era chrome; company settings is in the account menu, the book is the nav's "The book". --}}
    @livewire('forms.index', ['team' => $team])
</x-app-layout>
