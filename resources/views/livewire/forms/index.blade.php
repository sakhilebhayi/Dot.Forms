<div style="display: flex; flex-direction: column; gap: 28px;">
    <!-- Header -->
    <div style="display: flex; flex-direction: column; gap: 24px; align-items: flex-start; margin-bottom: 12px;">
        <div>
            <h2 class="display" style="font-size: 24px; font-weight: 800; margin: 0 0 6px;">Forms</h2>
            <p style="font-size: 14px; color: #6B7280; margin: 0;">Create, manage, and analyze forms for your team.</p>
        </div>

        <div style="display: flex; align-items: center; gap: 12px; width: 100%;">
            <a href="{{ route('teams.forms.ai-builder', $team) }}" style="display: inline-flex; align-items: center; gap: 8px; background: white; color: #1A1A1A; font-weight: 600; font-size: 14px; padding: 10px 18px; border-radius: 10px; border: 1px solid #E5E7EB; text-decoration: none; transition: border-color .12s, background .12s; cursor: pointer;" onmouseover="this.style.borderColor='var(--yellow)'; this.style.background='var(--yellow-light)'" onmouseout="this.style.borderColor='#E5E7EB'; this.style.background='white'">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm3.5-9c.83 0 1.5-.67 1.5-1.5S16.33 8 15.5 8 14 8.67 14 9.5s.67 1.5 1.5 1.5zm-7 0c.83 0 1.5-.67 1.5-1.5S9.33 8 8.5 8 7 8.67 7 9.5 7.67 11 8.5 11zm3.5 6.5c2.33 0 4.31-1.46 5.11-3.5H6.89c.8 2.04 2.78 3.5 5.11 3.5z"/></svg>
                AI Builder
            </a>
            <button wire:click="createForm" style="display: inline-flex; align-items: center; gap: 8px; background: var(--yellow); color: #1A1A1A; font-weight: 700; font-size: 14px; padding: 10px 18px; border-radius: 10px; border: none; text-decoration: none; transition: background .15s, transform .1s; box-shadow: 0 4px 12px rgba(245,184,0,.3); cursor: pointer;" onmouseover="this.style.background='var(--yellow-dark)'; this.style.transform='translateY(-1px)'" onmouseout="this.style.background='var(--yellow)'; this.style.transform='translateY(0)'">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
                Create New Form
            </button>
        </div>
    </div>

    <!-- Status Message -->
    @if (session('status'))
        <div style="background: #DCFCE7; border: 1px solid #86EFAC; color: #15803D; border-radius: 10px; padding: 12px 16px; font-size: 13px; font-weight: 500;">
            {{ session('status') }}
        </div>
    @endif

    <!-- Search and Filter Bar -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 12px;">
        <div>
            <input aria-label="Search forms"
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Search forms..."
                style="width: 100%; height: 44px; background: white; border: 1.5px solid #E5E7EB; border-radius: 10px; padding: 0 14px; font-size: 14px; color: #1A1A1A; outline: none; transition: border-color .15s; font-family: 'Inter', sans-serif;"
                onfocus="this.style.borderColor='var(--yellow)'" onblur="this.style.borderColor='#E5E7EB'">
        </div>
        <select aria-label="Filter by status" wire:model.live="status" style="width: 100%; height: 44px; background: white; border: 1.5px solid #E5E7EB; border-radius: 10px; padding: 0 14px; font-size: 14px; color: #1A1A1A; outline: none; transition: border-color .15s; font-family: 'Inter', sans-serif; cursor: pointer;" onfocus="this.style.borderColor='var(--yellow)'" onblur="this.style.borderColor='#E5E7EB'">
            <option value="all">All forms</option>
            <option value="published">Published</option>
            <option value="draft">Draft</option>
            <option value="archived">Archived</option>
        </select>
    </div>

    <!-- Forms Table -->
    <div style="overflow: hidden; border-radius: 12px; border: 1px solid #F0F0F0; box-shadow: 0 2px 12px rgba(0,0,0,.06);">
        <table style="width: 100%;">
            <thead style="background: #FAFAFA; border-bottom: 1px solid #F0F0F0;">
                <tr>
                    <th style="padding: 16px 20px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: #6B7280;">Title</th>
                    <th style="padding: 16px 20px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: #6B7280;">Status</th>
                    <th style="padding: 16px 20px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: #6B7280;">Updated</th>
                    <th style="padding: 16px 20px; text-align: right; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: #6B7280;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($forms as $form)
                    <tr style="border-top: 1px solid #F0F0F0; transition: background .12s;">
                        <td style="padding: 16px 20px; border-right: 1px solid #F0F0F0;">
                            <div style="font-size: 14px; font-weight: 600; color: #1A1A1A; margin-bottom: 4px;">{{ $form->title }}</div>
                            <div style="font-size: 12px; color: #6B7280;">/{{ $form->slug }}</div>
                        </td>
                        <td style="padding: 16px 20px; border-right: 1px solid #F0F0F0;">
                            @if ($form->archived_at)
                                <span style="display: inline-block; padding: 4px 10px; background: #F3F4F6; color: #6B7280; border-radius: 6px; font-size: 12px; font-weight: 500;">Archived</span>
                            @elseif ($form->is_published)
                                <span style="display: inline-block; padding: 4px 10px; background: #DCFCE7; color: #15803D; border-radius: 6px; font-size: 12px; font-weight: 500;">Published</span>
                            @else
                                <span style="display: inline-block; padding: 4px 10px; background: var(--yellow-light); color: var(--yellow-dark); border-radius: 6px; font-size: 12px; font-weight: 500;">Draft</span>
                            @endif
                        </td>
                        <td style="padding: 16px 20px; border-right: 1px solid #F0F0F0;">
                            <span style="font-size: 13px; color: #6B7280;">{{ $form->updated_at->diffForHumans() }}</span>
                        </td>
                        <td style="padding: 16px 20px; text-align: right;">
                            <div style="display: flex; align-items: center; gap: 6px; justify-content: flex-end;">
                                <a href="{{ route('teams.forms.builder', ['team' => $team, 'form' => $form]) }}" style="padding: 6px 10px; font-size: 12px; font-weight: 500; color: #374151; background: white; border: 1px solid #E5E7EB; border-radius: 6px; text-decoration: none; transition: all .12s; cursor: pointer;" onmouseover="this.style.borderColor='var(--yellow)'; this.style.backgroundColor='var(--yellow-light)'" onmouseout="this.style.borderColor='#E5E7EB'; this.style.backgroundColor='white'">Edit</a>
                                <a href="{{ route('teams.forms.submissions', ['team' => $team, 'form' => $form]) }}" style="padding: 6px 10px; font-size: 12px; font-weight: 500; color: #374151; background: white; border: 1px solid #E5E7EB; border-radius: 6px; text-decoration: none; transition: all .12s; cursor: pointer;" onmouseover="this.style.borderColor='var(--yellow)'; this.style.backgroundColor='var(--yellow-light)'" onmouseout="this.style.borderColor='#E5E7EB'; this.style.backgroundColor='white'">Responses</a>
                                <button wire:click="duplicateForm({{ $form->id }})" style="padding: 6px 10px; font-size: 12px; font-weight: 500; color: #374151; background: white; border: 1px solid #E5E7EB; border-radius: 6px; text-decoration: none; transition: all .12s; cursor: pointer;" onmouseover="this.style.borderColor='var(--yellow)'; this.style.backgroundColor='var(--yellow-light)'" onmouseout="this.style.borderColor='#E5E7EB'; this.style.backgroundColor='white'">Duplicate</button>
                                <button wire:click="archiveForm({{ $form->id }})" style="padding: 6px 10px; font-size: 12px; font-weight: 500; color: #6B7280; background: white; border: 1px solid #E5E7EB; border-radius: 6px; text-decoration: none; transition: all .12s; cursor: pointer;" onmouseover="this.style.borderColor='#9CA3AF'; this.style.backgroundColor='#F3F4F6'" onmouseout="this.style.borderColor='#E5E7EB'; this.style.backgroundColor='white'">Archive</button>
                                <button wire:click="deleteForm({{ $form->id }})" wire:confirm="Are you sure you want to delete this form? This action cannot be undone." style="padding: 6px 10px; font-size: 12px; font-weight: 500; color: var(--red); background: white; border: 1px solid #FCA5A5; border-radius: 6px; text-decoration: none; transition: all .12s; cursor: pointer;" onmouseover="this.style.borderColor='var(--red)'; this.style.backgroundColor='var(--red-light)'" onmouseout="this.style.borderColor='#FCA5A5'; this.style.backgroundColor='white'">Delete</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="padding: 48px 20px; text-align: center;">
                            <div style="font-size: 48px; margin-bottom: 16px;">📝</div>
                            <p style="font-size: 14px; color: #6B7280; margin: 0;">No forms found. Create one to get started.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div style="display: flex; justify-content: center;">
        {{ $forms->links() }}
    </div>
</div>
