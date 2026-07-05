<?php

namespace App\Modules\ImportExport\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImportSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'type'              => $this->type,
            'status'            => $this->status,
            'mode'              => $this->mode,
            'original_filename' => $this->original_filename,

            // Row counters
            'total_rows'    => $this->total_rows,
            'valid_rows'    => $this->valid_rows,
            'error_rows'    => $this->error_rows,
            'warning_rows'  => $this->warning_rows,
            'imported_rows' => $this->imported_rows,
            'skipped_rows'  => $this->skipped_rows,

            // Mapping & result
            'column_mapping' => $this->column_mapping,
            'summary'        => $this->summary,
            'error_message'  => $this->error_message,

            // Timestamps
            'analyzed_at'  => $this->analyzed_at?->toIso8601String(),
            'approved_at'  => $this->approved_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'approved_by'  => $this->approved_by,
            'performed_by' => $this->performed_by,
            'created_at'   => $this->created_at?->toIso8601String(),
            'updated_at'   => $this->updated_at?->toIso8601String(),

            // Preview rows (when loaded with relationship)
            'rows' => $this->whenLoaded('rows', fn() =>
                $this->rows->map(fn($row) => [
                    'id'          => $row->id,
                    'row_number'  => $row->row_number,
                    'status'      => $row->status,
                    'action'      => $row->action,
                    'mapped_data' => $row->mapped_data,
                    'errors'      => $row->errors,
                    'warnings'    => $row->warnings,
                    'entity_id'   => $row->entity_id,
                ])
            ),
        ];
    }
}
