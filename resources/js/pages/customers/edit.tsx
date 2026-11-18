import CourierCrudForm, { CrudField } from '@/components/courier-crud-form';

interface Customer {
    id: number;
    name: string;
    address: string;
}

const fields: CrudField[] = [
    { name: 'name', label: 'Customer name', required: true },
    { name: 'address', label: 'Address', required: true },
];

export default function EditCustomer({ customer }: { customer: Customer }) {
    return (
        <CourierCrudForm
            title="Edit customer"
            resource="Customers"
            action={route('customers.update', customer.id)}
            method="put"
            cancelHref={route('customers.index')}
            values={{ name: customer.name, address: customer.address }}
            fields={fields}
        />
    );
}
