import CourierCrudForm, { CrudField } from '@/components/courier-crud-form';

const fields: CrudField[] = [
    { name: 'name', label: 'Customer name', required: true },
    { name: 'address', label: 'Address', required: true },
];

export default function CreateCustomer() {
    return (
        <CourierCrudForm
            title="Add customer"
            resource="Customers"
            action={route('customers.store')}
            method="post"
            cancelHref={route('customers.index')}
            values={{ name: '', address: '' }}
            fields={fields}
        />
    );
}
