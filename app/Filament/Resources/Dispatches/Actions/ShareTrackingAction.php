<?php

namespace App\Filament\Resources\Dispatches\Actions;

use App\Enums\DispatchStatus;
use App\Models\Dispatch;
use App\Support\Permissions;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * Comparte el seguimiento del servicio: un enlace público para el
 * paciente, su familia o el hospital, listo para mandar por WhatsApp.
 */
class ShareTrackingAction
{
    public static function make(): Action
    {
        return Action::make('shareTracking')
            ->label('Compartir seguimiento')
            ->icon(Heroicon::OutlinedShare)
            ->color('primary')
            ->visible(fn (Dispatch $record) => Permissions::canShareTracking() && ! in_array($record->status, [DispatchStatus::Cancelled], true))
            ->modalHeading(fn (Dispatch $record) => "Seguimiento de {$record->code}")
            ->modalDescription('Quien tenga el enlace ve la unidad en el mapa, el estado y el tiempo estimado de llegada. No se muestran datos del paciente.')
            ->modalContent(function (Dispatch $record) {
                $url = $record->shareTracking();
                $text = rawurlencode("Puede seguir la ambulancia en tiempo real aquí: {$url}");
                $whatsapp = "https://wa.me/?text={$text}";

                return new HtmlString(
                    '<div style="display:flex;flex-direction:column;gap:12px">'
                    . '<input readonly onclick="this.select()" value="' . e($url) . '" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:8px;font-family:ui-monospace,monospace;font-size:13px;background:transparent;color:inherit">'
                    . '<div style="display:flex;gap:8px;flex-wrap:wrap">'
                    . '<a href="' . e($whatsapp) . '" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:6px;background:#16a34a;color:#fff;padding:9px 14px;border-radius:8px;font-weight:600;font-size:14px;text-decoration:none">Enviar por WhatsApp</a>'
                    . '<a href="' . e($url) . '" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:6px;border:1px solid #d1d5db;padding:9px 14px;border-radius:8px;font-weight:600;font-size:14px;text-decoration:none;color:inherit">Abrir</a>'
                    . '</div></div>'
                );
            })
            // Si el enlace llegó a quien no debía, se anula y se comparte uno nuevo
            ->modalSubmitAction(fn (Action $action) => Permissions::allows('tracking', 'delete')
                ? $action->label('Anular enlace')->color('danger')
                : false)
            ->action(function (Dispatch $record) {
                $record->revokeTracking();

                Notification::make()
                    ->title('Enlace anulado')
                    ->body('Quien lo tenga ya no puede ver el servicio. Puede compartir uno nuevo.')
                    ->success()
                    ->send();
            })
            ->modalCancelActionLabel('Cerrar');
    }
}
