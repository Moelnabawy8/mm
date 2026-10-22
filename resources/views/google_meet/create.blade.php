<style>
    .multi-select {
        position: relative;
        width: 100%;
    }

    .multi-select-header {
        border: 1px solid #ccc;
        padding: 10px;
        background: #fff;
        cursor: pointer;
        border-radius: 6px;
        display: flex;
        justify-content: space-between;
    }

    .multi-select-dropdown {
        display: none;
        position: absolute;
        width: 100%;
        background: white;
        border: 1px solid #ccc;
        margin-top: 2px;
        border-radius: 6px;
        max-height: 200px;
        overflow-y: auto;
        z-index: 20;
        padding: 10px;
    }

    .multi-select-item {
        display: block;
        padding: 4px 0;
        cursor: pointer;
    }

    .multi-select-item input {
        margin-right: 8px;
    }

    .arrow {
        font-size: 12px;
    }
</style>
{{ Form::open(['route' => 'google-meet.store', 'class' => 'needs-validation']) }}
<div class="modal-body">

    <div class="row">
        <div class="col-12 form-group">
            {{ Form::label('meet_name', __('Meeting Name'), ['class' => 'form-label']) }}
            {{ Form::text('meet_name', null, ['class' => 'form-control', 'required' => 'required']) }}
        </div>
        <div class="col-12 form-group">
            {{ Form::label('meet_link', __('Google Meet Link'), ['class' => 'form-label']) }}
            {{ Form::text('meet_link', null, ['class' => 'form-control', 'placeholder' => 'https://meet.google.com/xxx-xxxx-xxx', 'required' => 'required']) }}
        </div>
        <div class="col-6 form-group">
            {{ Form::label('start_date', __('Start Date & Time'), ['class' => 'form-label']) }}
            <input type="datetime-local" name="start_date" class="form-control" value="{{ old('start_date') }}" required>
        </div>
        <div class="col-6 form-group">
            {{ Form::label('end_date', __('End Date & Time'), ['class' => 'form-label']) }}
            <input type="datetime-local" name="end_date" class="form-control" value="{{ old('end_date') }}" required>
        </div>
        <div class="col-12 form-group">
            <label class="form-label">Meet Members</label>

            <div class="multi-select">
                <div class="multi-select-header" onclick="toggleDropdown()">
                    <span id="selected-count">Select members</span>
                    <span class="arrow">▼</span>
                </div>

                <div class="multi-select-dropdown" id="dropdown">
                    @foreach ($users as $user)
                        <label class="multi-select-item">
                            <input type="checkbox" name="members[]" value="{{ $user->id }}"
                                onchange="updateSelected()">
                            {{ $user->name }}
                        </label>
                    @endforeach
                </div>
            </div>
        </div>

    </div>

</div>
<div class="modal-footer">
    <input type="button" value="{{ __('Cancel') }}" class="btn btn-secondary" data-bs-dismiss="modal">
    <input type="submit" value="{{ __('Create') }}" class="btn btn-primary">
</div>
{{ Form::close() }}
<script>
    function toggleDropdown() {
        document.getElementById("dropdown").style.display =
            document.getElementById("dropdown").style.display === "block" ?
            "none" :
            "block";
    }

    function updateSelected() {
        const checked = document.querySelectorAll('input[name="members[]"]:checked');
        const header = document.getElementById("selected-count");

        if (checked.length === 0) {
            header.textContent = "Select members";
        } else {
            header.textContent = checked.length + " selected";
        }
    }

    // اغلاق الـ dropdown لما تضغط برا
    document.addEventListener("click", function(e) {
        const box = document.querySelector(".multi-select");
        const dropdown = document.getElementById("dropdown");

        if (!box.contains(e.target)) {
            dropdown.style.display = "none";
        }
    });
</script>
