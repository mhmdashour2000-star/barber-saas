<x-layouts.company>
    <x-slot:title>Edit Service: {{ $service->name }} - Barbar SaaS</x-slot:title>

    <div class="mb-6 flex items-center justify-between">
        <div>
            <div class="flex items-center space-x-2 text-xs text-gray-500 mb-1">
                <a href="{{ route('company.services.index') }}" class="hover:text-blue-600 transition">Services</a>
                <span>/</span>
                <span>Edit</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 tracking-tight">Edit Service</h1>
        </div>
        <a href="{{ route('company.services.index') }}" class="inline-flex items-center px-3 py-2 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition shadow-xs">
            &larr; Back to Services
        </a>
    </div>

    <!-- Main Card -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden max-w-3xl">
        <div class="p-6 sm:p-8">
            <form action="{{ route('company.services.update', $service->id) }}" method="POST">
                @csrf
                @method('PUT')

                <!-- Basic Information Section -->
                <div class="mb-8">
                    <h2 class="text-base font-bold text-gray-900 border-b border-gray-100 pb-2 mb-4">Service Details</h2>

                    <div class="space-y-4">
                        <!-- Service Name -->
                        <div>
                            <label for="name" class="block mb-1.5 text-xs font-bold text-gray-700 uppercase tracking-wider">
                                Service Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="name" name="name" value="{{ old('name', $service->name) }}" required
                                   placeholder="e.g. Classic Men's Haircut, Beard Trim, Deluxe Styling"
                                   class="block w-full p-2.5 text-sm text-gray-900 border @error('name') border-red-500 bg-red-50 @else border-gray-300 bg-gray-50/50 @enderror rounded-lg focus:ring-blue-500 focus:border-blue-500 transition">
                            @error('name')
                                <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Description -->
                        <div>
                            <label for="description" class="block mb-1.5 text-xs font-bold text-gray-700 uppercase tracking-wider">
                                Description <span class="text-gray-400 font-normal lowercase">(optional)</span>
                            </label>
                            <textarea id="description" name="description" rows="3"
                                      placeholder="Provide a brief summary of what this service includes..."
                                      class="block w-full p-2.5 text-sm text-gray-900 border @error('description') border-red-500 bg-red-50 @else border-gray-300 bg-gray-50/50 @enderror rounded-lg focus:ring-blue-500 focus:border-blue-500 transition">{{ old('description', $service->description) }}</textarea>
                            @error('description')
                                <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Price & Duration Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Price -->
                            <div>
                                <label for="price" class="block mb-1.5 text-xs font-bold text-gray-700 uppercase tracking-wider">
                                    Price (TRY) <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <input type="number" id="price" name="price" step="0.01" min="0" max="999999.99"
                                           value="{{ old('price', number_format($service->price_decimal, 2, '.', '')) }}" required
                                           placeholder="e.g. 250.00"
                                           class="block w-full p-2.5 pr-10 text-sm text-gray-900 font-mono border @error('price') border-red-500 bg-red-50 @else border-gray-300 bg-gray-50/50 @enderror rounded-lg focus:ring-blue-500 focus:border-blue-500 transition">
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-xs font-bold text-gray-500">
                                        ₺
                                    </div>
                                </div>
                                <p class="mt-1 text-[11px] text-gray-500">Stored safely as integer minor units: {{ $service->price_minor_units }} kuruş.</p>
                                @error('price')
                                    <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Duration in Minutes -->
                            <div>
                                <label for="duration_minutes" class="block mb-1.5 text-xs font-bold text-gray-700 uppercase tracking-wider">
                                    Duration (Minutes) <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <input type="number" id="duration_minutes" name="duration_minutes" min="5" max="720" step="5"
                                           value="{{ old('duration_minutes', $service->duration_minutes) }}" required
                                           placeholder="e.g. 30, 45, 60"
                                           class="block w-full p-2.5 pr-12 text-sm text-gray-900 border @error('duration_minutes') border-red-500 bg-red-50 @else border-gray-300 bg-gray-50/50 @enderror rounded-lg focus:ring-blue-500 focus:border-blue-500 transition">
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-xs text-gray-500">
                                        min
                                    </div>
                                </div>
                                <p class="mt-1 text-[11px] text-gray-500">Required for scheduling slot intervals.</p>
                                @error('duration_minutes')
                                    <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- Active Status -->
                        <div class="pt-2">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="active" value="1" {{ old('active', $service->active ? '1' : '0') == '1' ? 'checked' : '' }} class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                <span class="ml-3 text-xs font-bold text-gray-700 uppercase tracking-wider">Service Active</span>
                            </label>
                            <p class="text-[11px] text-gray-500 mt-1">Inactive services are excluded from new booking availability.</p>
                        </div>
                    </div>
                </div>

                <!-- Staff Assignment Section -->
                <div class="mb-8">
                    <div class="flex items-center justify-between border-b border-gray-100 pb-2 mb-4">
                        <div>
                            <h2 class="text-base font-bold text-gray-900">Assigned Staff Members</h2>
                            <p class="text-xs text-gray-500 mt-0.5">Select which employees at your salon are qualified to perform this service.</p>
                        </div>
                        <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">
                            {{ $employees->count() }} Available Staff
                        </span>
                    </div>

                    @php
                        $assignedIds = old('employee_ids', $service->employees->pluck('id')->all());
                    @endphp

                    @if($employees->isEmpty())
                        <div class="p-4 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-800 flex items-start space-x-2">
                            <svg class="w-4 h-4 flex-shrink-0 text-amber-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                            <div>
                                <span class="font-bold">No staff registered.</span>
                                Add employees in Staff Management before they can be assigned to provide services.
                            </div>
                        </div>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-60 overflow-y-auto p-1 border border-gray-200 rounded-xl">
                            @foreach($employees as $employee)
                                <label class="flex items-center p-3 rounded-lg border border-gray-100 hover:bg-blue-50/50 cursor-pointer transition">
                                    <input type="checkbox" name="employee_ids[]" value="{{ $employee->id }}"
                                           {{ in_array($employee->id, $assignedIds) ? 'checked' : '' }}
                                           class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500">
                                    <div class="ml-3">
                                        <div class="text-xs font-bold text-gray-900">{{ $employee->name }}</div>
                                        <div class="text-[11px] text-gray-500">
                                            {{ '@' . $employee->username }}
                                            @if(!$employee->active)
                                                <span class="text-amber-600 font-semibold">(Inactive)</span>
                                            @endif
                                        </div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    @endif

                    @error('employee_ids')
                        <p class="mt-2 text-xs text-red-600 font-medium">{{ $message }}</p>
                    @enderror
                    @error('employee_ids.*')
                        <p class="mt-2 text-xs text-red-600 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Form Action Buttons -->
                <div class="flex items-center justify-end space-x-3 pt-4 border-t border-gray-100">
                    <a href="{{ route('company.services.index') }}"
                       class="px-4 py-2.5 text-xs font-bold text-gray-700 uppercase tracking-wider hover:bg-gray-100 rounded-lg transition">
                        Cancel
                    </a>
                    <button type="submit"
                            class="px-5 py-2.5 text-xs font-bold text-white uppercase tracking-wider bg-blue-600 hover:bg-blue-700 rounded-lg shadow-sm focus:ring-4 focus:ring-blue-300 transition">
                        Update Service
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.company>
