import CourierCrudForm, { CrudField } from '@/components/courier-crud-form';

const fields: CrudField[] = [{ name: 'name', label: 'Transit hub name', required: true }];

export default function CreateTransitHub() {
    return (
        <CourierCrudForm
            title="Add transit hub"
            resource="Transit hubs"
            action={route('transit-hubs.store')}
            method="post"
            cancelHref={route('transit-hubs.index')}
            values={{ name: '' }}
            fields={fields}
        />
    );
}
