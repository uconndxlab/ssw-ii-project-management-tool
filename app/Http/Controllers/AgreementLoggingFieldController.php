<?php

namespace App\Http\Controllers;

use App\Models\AgreementLoggingField;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AgreementLoggingFieldController extends Controller
{
    public function index(Request $request)
    {
        $query = AgreementLoggingField::query();

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('help_text', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        if ($request->filled('field_type')) {
            $query->where('field_type', $request->field_type);
        }

        $sortBy = $request->get('sort_by', 'sort_order');
        $sortDir = $request->get('sort_dir', 'asc');

        if ($sortBy === 'sort_order') {
            $query->orderBy('sort_order')->orderBy('name');
        } else {
            $query->orderBy($sortBy, $sortDir);
        }

        $loggingFields = $query->paginate(20)->withQueryString();

        return view('agreement-logging-fields.index', compact('loggingFields'));
    }

    public function create()
    {
        $fieldTypes = AgreementLoggingField::fieldTypes();

        return view('agreement-logging-fields.create', compact('fieldTypes'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateField($request, 'agreement_logging_fields');
        $validated['is_active'] = $request->boolean('is_active', true);

        AgreementLoggingField::create($validated);

        return redirect()->route('agreement-logging-fields.index')
            ->with('success', 'Agreement logging field created successfully.');
    }

    public function show(AgreementLoggingField $agreementLoggingField)
    {
        $agreementLoggingField->load(['agreements' => function ($query) {
            $query->select('id', 'name')->orderBy('name');
        }]);

        return view('agreement-logging-fields.show', compact('agreementLoggingField'));
    }

    public function edit(AgreementLoggingField $agreementLoggingField)
    {
        $fieldTypes = AgreementLoggingField::fieldTypes();

        return view('agreement-logging-fields.edit', compact('agreementLoggingField', 'fieldTypes'));
    }

    public function update(Request $request, AgreementLoggingField $agreementLoggingField)
    {
        $validated = $this->validateField($request, 'agreement_logging_fields', $agreementLoggingField->id);
        $validated['is_active'] = $request->boolean('is_active');

        if ($validated['name'] !== $agreementLoggingField->name) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $agreementLoggingField->update($validated);

        return redirect()->route('agreement-logging-fields.index')
            ->with('success', 'Agreement logging field updated successfully.');
    }

    public function destroy(AgreementLoggingField $agreementLoggingField)
    {
        $agreementCount = $agreementLoggingField->agreements()->count();

        if ($agreementCount > 0) {
            return back()->with('error', "Cannot delete this field. It is currently used by {$agreementCount} agreement(s).");
        }

        $agreementLoggingField->delete();

        return redirect()->route('agreement-logging-fields.index')
            ->with('success', 'Agreement logging field deleted successfully.');
    }

    private function validateField(Request $request, string $table, ?int $ignoreId = null): array
    {
        $uniqueRule = 'required|string|max:255|unique:' . $table . ',name';
        if ($ignoreId) {
            $uniqueRule .= ',' . $ignoreId;
        }

        $validated = $request->validate([
            'name' => $uniqueRule,
            'field_type' => 'required|in:number,decimal,text,textarea,checkbox,select,document',
            'help_text' => 'nullable|string|max:1000',
            'options_json' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if ($validated['field_type'] === 'select' && !empty($validated['options_json'])) {
            $options = json_decode($validated['options_json'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw ValidationException::withMessages([
                    'options_json' => 'Invalid JSON format.',
                ]);
            }
            $validated['options_json'] = $options;
        } else {
            $validated['options_json'] = null;
        }

        return $validated;
    }
}
