@props([
    'id' => Str::uuid()->toString(),
    'label' => null,
    'icon' => null,
    'hint' => null,
    'hintClass' => 'label-text-alt text-gray-400 py-1 pb-0',

    'errorField' => null,
    'errorClass' => 'text-red-500 label-text-alt p-1',
    'omitError' => false,
    'firstErrorOnly' => false,
])

@php
    $modelName = $attributes->whereStartsWith('wire:model')->first();
    $errorFieldName = $errorField ?? $modelName;
    $id = $id == $modelName ? $modelName : "{$id}{$modelName}";
@endphp


<div
    x-cloak
    x-data="{
        datePickerOpen: false,
        datePickerValue: $wire.entangle(@js($modelName)),
        datePickerMonth: '',
        datePickerYear: '',
        datePickerDay: '',
        datePickerDaysInMonth: [],
        datePickerBlankDaysInMonth: [],
        datePickerMonthNames: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
        datePickerDays: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
        datePickerDayClicked(day) {
            let selectedDate = new Date(this.datePickerYear, this.datePickerMonth, day);
            this.datePickerDay = day;
            this.datePickerValue = this.dateToValue(selectedDate);
            this.datePickerIsSelectedDate(day);
            this.datePickerOpen = false;
        },
        datePickerPreviousMonth(){
            if (this.datePickerMonth == 0) { 
                this.datePickerYear--; 
                this.datePickerMonth = 12; 
            } 
            this.datePickerMonth--;
            this.datePickerCalculateDays();
        },
        datePickerNextMonth(){
            if (this.datePickerMonth == 11) { 
                this.datePickerMonth = 0; 
                this.datePickerYear++; 
            } else { 
                this.datePickerMonth++; 
            }
            this.datePickerCalculateDays();
        },
        datePickerIsSelectedDate(day) {
            const d = new Date(this.datePickerYear, this.datePickerMonth, day);
            return this.datePickerValue === this.dateToValue(d) ? true : false;
        },
        datePickerIsToday(day) {
            const today = new Date();
            const d = new Date(this.datePickerYear, this.datePickerMonth, day);
            return today.toDateString() === d.toDateString() ? true : false;
        },
        datePickerCalculateDays() {
            let daysInMonth = new Date(this.datePickerYear, this.datePickerMonth + 1, 0).getDate();
            // find where to start calendar day of week
            let dayOfWeek = new Date(this.datePickerYear, this.datePickerMonth).getDay();
            let blankdaysArray = [];
            for (var i = 1; i <= dayOfWeek; i++) {
                blankdaysArray.push(i);
            }
            let daysArray = [];
            for (var i = 1; i <= daysInMonth; i++) {
                daysArray.push(i);
            }
            this.datePickerBlankDaysInMonth = blankdaysArray;
            this.datePickerDaysInMonth = daysArray;
        },
        dateToValue(d) {
            d = this.parseDate(d)
            let formattedDate = ('0' + d.getDate()).slice(-2); 
            let formattedMonthInNumber = ('0' + (parseInt(d.getMonth()) + 1)).slice(-2);
            let formattedYear = d.getFullYear();

            return `${formattedYear}-${formattedMonthInNumber}-${formattedDate}`;
        },
        parseDate(d) {
            date = new Date();
            let userTimezoneOffset = date.getTimezoneOffset() * 60000;
            return new Date(Date.parse(d) + userTimezoneOffset);
        }
    }" 
    x-init="
        currentDate = new Date();
        if (datePickerValue) {
            
            currentDate = parseDate(datePickerValue)
            
        }
        datePickerMonth = currentDate.getMonth();
        datePickerYear = currentDate.getFullYear();
        datePickerDay = currentDate.getDay();
        datePickerValue = currentDate.toISOString().slice(0, 10);
        datePickerCalculateDays();
    "
>
    {{-- STANDARD LABEL --}}
    @if($label)
        <label for="{{ $id }}" class="pt-0 label label-text font-semibold">
            <span>
                {{ $label }}

                @if($attributes->get('required'))
                    <span class="text-error">*</span>
                @endif
            </span>
        </label>
    @endif

    <div 
        class="flex-1 relative"
    >
        {{-- DESKTOP --}}
        <div
            x-ref="datePickerInput"
            x-html="parseDate(datePickerValue).toLocaleDateString()"
            x-on:keydown.escape="datePickerOpen=false"
            @click="datePickerOpen=true"

            {{ $attributes->class([
                    "hidden md:block input py-3 px-4 input-primary w-full peer appearance-none",
                    'ps-10' => ($icon),
                    'border border-dashed' => $attributes->has('readonly') && $attributes->get('readonly') == true,
                    'input-error' => $errors->has($errorFieldName)
                ]) }}
        ></div>

        {{-- MOBILE --}}
        <input 
            type="date"
            x-model="datePickerValue"
            placeholder="Select date"
            id="{{ $id }}"

            {{ $attributes->class([
                    "block md:hidden input input-primary w-full peer appearance-none",
                    'ps-10' => ($icon),
                    'border border-dashed' => $attributes->has('readonly') && $attributes->get('readonly') == true,
                    'input-error' => $errors->has($errorFieldName)
                ]) }}
        />

        <div @click="
                datePickerOpen=!datePickerOpen;
                if(datePickerOpen) {
                    $refs.datePickerInput.focus()
                }
            "
            class="hidden md:block absolute top-0 right-0 p-3 cursor-pointer text-neutral-400 hover:text-neutral-500"
        >
            <x-ib-icon name="o-calendar" />
        </div>

        <div  
            x-show="datePickerOpen"
            x-transition
            @click.away="datePickerOpen = false" 
            class="
                p-4
                mt-12
                top-0
                left-0
                max-w-lg
                w-[17rem]
                absolute
                z-50
                bg-base-100
                dark:bg-base-300
                rounded-box
                shadow-md   
            "
        >
            <div class="flex justify-between items-center mb-2">
                <div>
                    <span x-text="datePickerMonthNames[datePickerMonth]" class="text-lg font-bold"></span>
                    <span x-text="datePickerYear" class="ml-1 text-lg font-normal text-gray-600"></span>
                </div>
                <div>
                    <button @click="datePickerPreviousMonth()" type="button" class="inline-flex p-1 rounded-full transition duration-100 ease-in-out cursor-pointer focus:outline-none focus:shadow-outline hover:bg-accent/50">
                        <x-ib-icon name="o-chevron-left" />
                    </button>
                    <button @click="datePickerNextMonth()" type="button" class="inline-flex p-1 rounded-full transition duration-100 ease-in-out cursor-pointer focus:outline-none focus:shadow-outline hover:bg-accent/50">
                        <x-ib-icon name="o-chevron-right" />
                    </button>
                </div>
            </div>
            <div class="grid grid-cols-7 mb-3">
                <template x-for="(day, index) in datePickerDays" :key="index">
                    <div class="px-0.5">
                        <div x-text="day" class="text-xs font-medium text-center"></div>
                    </div>
                </template>
            </div>
            <div class="grid grid-cols-7">
                <template x-for="blankDay in datePickerBlankDaysInMonth">
                    <div class="p-1 text-sm text-center border border-transparent"></div>
                </template>
                <template x-for="(day, dayIndex) in datePickerDaysInMonth" :key="dayIndex">
                    <div class="px-0.5 mb-1 aspect-square">
                        <div 
                            x-text="day"
                            @click="datePickerDayClicked(day)" 
                            :class="{
                                'border border-accent/50': datePickerIsToday(day) == true, 
                                'hover:bg-neutral-800/70': datePickerIsToday(day) == false && datePickerIsSelectedDate(day) == false,
                                'text-primary-content bg-primary hover:bg-primary/50': datePickerIsSelectedDate(day) == true
                            }" 
                            class="flex justify-center items-center w-7 h-7 text-sm leading-none text-center rounded-full cursor-pointer"
                        ></div>
                    </div>
                </template>
            </div>
        </div>

        {{-- ICON --}}
        @if($icon)
            <x-ib-icon :name="$icon" class="absolute top-1/2 -translate-y-1/2 start-3 text-gray-400 pointer-events-none" />
        @endif

    </div>

    {{-- ERROR --}}
    @if(!$omitError && $errors->has($errorFieldName))
        @foreach($errors->get($errorFieldName) as $message)
            @foreach(Arr::wrap($message) as $line)
                <div class="{{ $errorClass }}" x-classes="text-red-500 label-text-alt p-1">{{ $line }}</div>
                @break($firstErrorOnly)
            @endforeach
            @break($firstErrorOnly)
        @endforeach
    @endif

    {{-- HINT --}}
    @if($hint)
        <div class="{{ $hintClass }}" x-classes="label-text-alt text-gray-400 py-1 pb-0">{{ $hint }}</div>
    @endif

</div>
