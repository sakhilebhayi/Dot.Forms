<nav style="background: rgba(255,255,255,.92); backdrop-filter: blur(12px); border-bottom: 1px solid #F0F0F0; position: sticky; top: 0; z-index: 50;">
    <div style="max-width: 1200px; margin: 0 auto; padding: 0 24px; height: 64px; display: flex; align-items: center; justify-content: space-between;">
        <!-- Logo -->
        <a href="{{ route('dashboard') }}" style="display: flex; align-items: center; gap: 10px; text-decoration: none;">
            <img src="{{ asset('images/dot_forms.png') }}" alt="Dot.Forms" style="height: 38px; width: 38px; object-fit: contain;">
            <span style="font-family: 'Sora', sans-serif; font-size: 18px; font-weight: 800; color: #1A1A1A; letter-spacing: -.01em;">dot<span style="color: var(--red);">.</span>forms</span>
        </a>

        <!-- Desktop Navigation Links -->
        <div style="display: none; gap: 8px; align-items: center;">
            @php
                $isDashboard = request()->routeIs('dashboard');
                $isAnalytics = request()->routeIs('dashboard.analytics');
                $isForms = request()->routeIs('teams.forms*');
            @endphp
            
            @if($isDashboard)
                <a href="{{ route('dashboard') }}" style="font-size: 14px; font-weight: 500; color: var(--yellow-dark); text-decoration: none; padding: 8px 16px; border-radius: 8px; transition: background .12s, color .12s; background: var(--yellow-light);">Dashboard</a>
            @else
                <a href="{{ route('dashboard') }}" style="font-size: 14px; font-weight: 500; color: #374151; text-decoration: none; padding: 8px 16px; border-radius: 8px; transition: background .12s, color .12s;">Dashboard</a>
            @endif

            @if($isAnalytics)
                <a href="{{ route('dashboard.analytics') }}" style="font-size: 14px; font-weight: 500; color: var(--yellow-dark); text-decoration: none; padding: 8px 16px; border-radius: 8px; transition: background .12s, color .12s; background: var(--yellow-light);">Analytics</a>
            @else
                <a href="{{ route('dashboard.analytics') }}" style="font-size: 14px; font-weight: 500; color: #374151; text-decoration: none; padding: 8px 16px; border-radius: 8px; transition: background .12s, color .12s;">Analytics</a>
            @endif

            @if (auth()->user()->currentTeam)
                @if($isForms)
                    <a href="{{ route('teams.forms', auth()->user()->currentTeam) }}" style="font-size: 14px; font-weight: 500; color: var(--yellow-dark); text-decoration: none; padding: 8px 16px; border-radius: 8px; transition: background .12s, color .12s; background: var(--yellow-light);">Forms</a>
                @else
                    <a href="{{ route('teams.forms', auth()->user()->currentTeam) }}" style="font-size: 14px; font-weight: 500; color: #374151; text-decoration: none; padding: 8px 16px; border-radius: 8px; transition: background .12s, color .12s;">Forms</a>
                @endif
            @endif
        </div>

        <!-- Right Side Menu -->
        <div style="display: flex; align-items: center; gap: 16px;">
            <!-- Team Dropdown -->
            @if (Laravel\Jetstream\Jetstream::hasTeamFeatures() && Auth::user()->currentTeam)
                <div class="relative" x-data="{ teamOpen: false }">
                    <button @click="teamOpen = !teamOpen" style="display: flex; align-items: center; gap: 6px; font-size: 14px; font-weight: 500; color: #374151; background: none; border: 1px solid #E5E7EB; padding: 8px 12px; border-radius: 8px; cursor: pointer; transition: border-color .12s;">
                        {{ Auth::user()->currentTeam->name }}
                        <svg style="width: 14px; height: 14px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>
                    <div @click.away="teamOpen = false" x-show="teamOpen" style="position: absolute; left: 0; top: 100%; margin-top: 8px; background: white; border: 1px solid #E5E7EB; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,.1); min-width: 200px; z-index: 50;">
                        <div style="padding: 8px; border-bottom: 1px solid #F0F0F0;">
                            <a href="{{ route('teams.show', Auth::user()->currentTeam->id) }}" style="display: block; padding: 8px 12px; font-size: 13px; color: #374151; text-decoration: none; border-radius: 6px; transition: background .12s;" class="hover:bg-gray-50">Team Settings</a>
                        </div>
                        @if (Auth::user()->allTeams()->count() > 1)
                            <div style="padding: 8px; border-top: 1px solid #F0F0F0;">
                                @foreach (Auth::user()->allTeams() as $team)
                                    @php
                                        $isCurrentTeam = $team->id === Auth::user()->currentTeam->id;
                                    @endphp
                                    {{-- Switching teams is a state change (writes current_team_id), not a
                                         navigable page -- Jetstream only exposes it as PUT current-team.update,
                                         there is no GET-able "teams.switch" route. --}}
                                    <form method="POST" action="{{ route('current-team.update') }}">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="team_id" value="{{ $team->id }}">
                                        <button type="submit" style="display: block; width: 100%; text-align: left; padding: 8px 12px; font-size: 13px; color: {{ $isCurrentTeam ? 'var(--yellow-dark)' : '#374151' }}; border: none; background: {{ $isCurrentTeam ? 'var(--yellow-light)' : 'none' }}; border-radius: 6px; transition: background .12s; cursor: pointer;" class="hover:bg-gray-50">{{ $team->name }}</button>
                                    </form>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- User Dropdown -->
            <div class="relative" x-data="{ userOpen: false }">
                <button @click="userOpen = !userOpen" style="display: flex; align-items: center; gap: 8px; background: none; border: none; cursor: pointer; padding: 0;">
                    @if (Laravel\Jetstream\Jetstream::managesProfilePhotos())
                        <img style="height: 32px; width: 32px; border-radius: 50%; object-fit: cover;" src="{{ Auth::user()->profile_photo_url }}" alt="{{ Auth::user()->name }}" />
                    @else
                        <div style="height: 32px; width: 32px; border-radius: 50%; background: var(--yellow); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; color: #1A1A1A;">{{ substr(Auth::user()->name, 0, 1) }}</div>
                    @endif
                </button>
                <div @click.away="userOpen = false" x-show="userOpen" style="position: absolute; right: 0; top: 100%; margin-top: 8px; background: white; border: 1px solid #E5E7EB; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,.1); min-width: 180px; z-index: 50;">
                    <div style="padding: 12px; border-bottom: 1px solid #F0F0F0;">
                        <div style="font-size: 13px; font-weight: 600; color: #1A1A1A;">{{ Auth::user()->name }}</div>
                        <div style="font-size: 12px; color: #6B7280;">{{ Auth::user()->email }}</div>
                    </div>
                    <div style="padding: 8px;">
                        <a href="{{ route('profile.show') }}" style="display: block; padding: 8px 12px; font-size: 13px; color: #374151; text-decoration: none; border-radius: 6px; transition: background .12s;" class="hover:bg-gray-50">Profile</a>
                        @if (Laravel\Jetstream\Jetstream::hasApiFeatures())
                            <a href="{{ route('api-tokens.index') }}" style="display: block; padding: 8px 12px; font-size: 13px; color: #374151; text-decoration: none; border-radius: 6px; transition: background .12s;" class="hover:bg-gray-50">API Tokens</a>
                        @endif
                        <form method="POST" action="{{ route('logout') }}" style="padding: 8px;">
                            @csrf
                            <button type="submit" style="width: 100%; padding: 8px 12px; font-size: 13px; color: var(--red); text-decoration: none; border: none; background: none; border-radius: 6px; transition: background .12s; cursor: pointer; text-align: left;" class="hover:bg-red-50">Log Out</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>
