import CourierCrudForm, { CrudField } from '@/components/courier-crud-form';

interface StatusLog {
    id: number;
    sequence: number;
    status: string;
    occurred_at: string;
    parcel: { tracking_number: string };
    transit_hub: { name: string } | null;
}

const fields: CrudField[] = [
    { name: 'status', label: 'Status', type: 'select', required: true },
    { name: 'current_hub_name', label: 'Transit hub' },
    { name: 'occurred_at', label: 'Date and time', type: 'datetime-local', required: true },
];

export default function EditShipmentStatusLog({ log, shipmentStatuses }: { log: StatusLog; shipmentStatuses: string[] }) {
    const statusFields = fields.map((field) => field.name === 'status'
        ? { ...field, options: shipmentStatuses.map((status) => ({ value: status, label: status.replaceAll('_', ' ') })) }
        : field);

    return (
        <CourierCrudForm
            title={`Edit status event #${log.sequence}`}
            resource="Status history"
            action={route('shipment-status-logs.update', log.id)}
            method="put"
            cancelHref={route('shipment-status-logs.index')}
            values={{
                status: log.status,
                current_hub_name: log.transit_hub?.name ?? '',
                occurred_at: log.occurred_at.slice(0, 16),
            }}
            fields={statusFields}
        />
    );
}
