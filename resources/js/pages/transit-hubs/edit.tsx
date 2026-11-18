import CourierCrudForm, { CrudField } from '@/components/courier-crud-form';

interface Hub {
    id: number;
    name: string;
}

const fields: CrudField[] = [{ name: 'name', label: 'Transit hub name', required: true }];

export default function EditTransitHub({ hub }: { hub: Hub }) {
    return (
        <CourierCrudForm
            title="Edit transit hub"
            resource="Transit hubs"
            action={route('transit-hubs.update', hub.id)}
            method="put"
            cancelHref={route('transit-hubs.index')}
            values={{ name: hub.name }}
            fields={fields}
        />
    );
}
