# PROGRESS REPORT - 1

## 1. Project Status

- **System Name:** Express Courier Package Express Delivery System
- **Date:** November 16, 2026
- **Current Completion:** 25%
- **Current Stage:** Backend architecture scaffolding and core workflow integration

## 2. List of Latest System Changes

| Module / Feature | Type (New / Update / Fix) | Short Summary of Change |
|---|---|---|
| Courier backend architecture and workflow scaffolding | Update | Established the five-table domain foundation and added starter controller and service files for the courier modules. Parcel and shipment-status workflows provide the initial functional foundation. |

## 3. Explanation of Each Change

### Courier backend architecture and workflow scaffolding

- **What changed:** The system is organized around five domain tables: customers, transit hubs, parcels, shipment status logs, and courier payments. Five migrations define these database tables, and five Eloquent models represent the domain records and their relationships. Controller files are present for Parcel, Shipment Status Log, Customer, Transit Hub, and Courier Payment. Starter service files are also present for the courier modules, alongside the existing `CourierService`. The Parcel and Shipment Status Log controllers support the two required focus workflows. The courier service logic handles input cleanup, tracking-number capitalization, address formatting, shipping-cost calculation, pending-payment updates, and sequential status logging.
- **Why it was needed:** Separating the system into domain tables helps reduce duplicated data and supports the required normalized database structure. Organizing controllers and services by responsibility provides a foundation for keeping business logic separate from request handling. The Parcel and Shipment Status Log workflows cover the central package creation and tracking needs of the system.
- **Current state:** The Parcel and Shipment Status Log workflows are implemented and the focused courier workflow test run passed 6 tests with 32 assertions. The Customer, Transit Hub, and Courier Payment controllers, as well as several service files, are still starter scaffolds and need their functionality completed. The focused test output reported PHP deprecation warnings, and the full test suite has not yet been verified.

## 4. Remaining Tasks Before Final Submission

| Unfinished Task | Responsible | Target Completion Date |
|---|---|---|
| Implement Customer, Transit Hub, and Courier Payment controller workflows and complete the related services. | Elsie L. Paclibar | November 20, 2026 |
| Complete required validation, route integration, and any needed interfaces for the remaining modules. | Elsie L. Paclibar | November 23, 2026 |
| Run migration and full regression tests; investigate relevant failures and deprecation warnings. | Elsie L. Paclibar | November 25, 2026 |
| Complete final system documentation, including the 3NF explanation, schema details, and test evidence. | Elsie L. Paclibar | November 27, 2026 |
| Commit and push the completed work to GitHub. | Elsie L. Paclibar | November 27, 2026 |

## 5. GitHub Repository

    Project repository: [https://github.com/c20245375paclibar-cyber/express-courier-package-delivery-system-mid-exam](https://github.com/c20245375paclibar-cyber/express-courier-package-delivery-system-mid-exam)

The current working copy contains untracked controller and service scaffolding. Commit and push those files before submission if they should be included in the repository link.

## 6. Submission Link

[Open the Express Courier Package Express Delivery System GitHub repository](https://github.com/c20245375paclibar-cyber/express-courier-package-delivery-system-mid-exam)
