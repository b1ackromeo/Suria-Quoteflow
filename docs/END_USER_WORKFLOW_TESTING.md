# QuoteFlow End-User Workflow Testing

Use this checklist to test QuoteFlow like a business user. It covers the MVP workflows that are currently implemented in the app.

## Test Environment

- Local URL: `http://127.0.0.1:8000`
- Login page: `http://127.0.0.1:8000/login`
- Demo seed command:

```powershell
& 'D:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe' artisan db:seed --class=DemoOperationsSeeder
```

Run the demo seed only in the local testing database. Do not run it on production.

## UAT Login Accounts

All UAT users use this password:

```text
Password123!
```

| Role | Email | What to Test |
| --- | --- | --- |
| Admin | `admin@quoteflow.test` | Full setup, all workflows, users, approvals, audit trail |
| Manager | `manager@quoteflow.test` | Approvals, reports, audit trail, operational review |
| Sales | `sales@quoteflow.test` | Customers, customer quotations, PO received records, customer invoices |
| Procurement | `procurement@quoteflow.test` | Suppliers, purchase requests, supplier quotations, purchase orders, goods/service receipts |
| Accounts | `accounts@quoteflow.test` | Payments, invoices, finance workflow actions |
| Viewer | `viewer@quoteflow.test` | Read-only dashboard, documents, and reports |

## UAT Rules

- Record the actual document number created by the system.
- After every Save, Submit, Approve, Issue, Receive, Match, Pay, or Close action, confirm the status chip changed correctly.
- Use the Related document field when creating the next document in a workflow.
- Preview and download at least one PDF, then export one CSV during testing.
- Upload one small attachment to a customer invoice and one to a supplier invoice.
- Check Audit Trail after completing the workflows.

## Workflow 1: User Login And Role Access

| Step | User | Action | Expected Result |
| --- | --- | --- | --- |
| 1 | Admin | Log in at `/login` | Dashboard opens |
| 2 | Admin | Open Users | User list opens |
| 3 | Manager | Open Users | Access is denied |
| 4 | Sales | Open Suppliers | Access is denied |
| 5 | Procurement | Open Customers | Access is denied |
| 6 | Accounts | Open Payments | Payments page opens |
| 7 | Viewer | Open Dashboard, Reports, document lists | Read pages open |

## Workflow 2: Master Data

| Step | User | Action | Expected Result |
| --- | --- | --- | --- |
| 1 | Sales or Admin | Create a customer | Customer appears in Customer list |
| 2 | Procurement or Admin | Create a supplier | Supplier appears in Supplier list |
| 3 | Sales, Procurement, or Admin | Create a product/service | Product or service appears in Product list |
| 4 | Viewer | Try to create master data | Access is denied |

## Workflow 3: Outgoing Customer Process

Business flow:

```text
Customer inquiry -> quotation -> approval -> PO received -> delivery/service completion -> invoice -> payment -> close
```

| Step | User | Action | Expected Result |
| --- | --- | --- | --- |
| 1 | Sales/Admin | Create Customer Quotation | Status is Draft |
| 2 | Sales/Admin | Submit for approval | Status is Pending Approval |
| 3 | Manager/Admin | Approve quotation | Status is Approved |
| 4 | Sales/Admin | Record PO Received and select the quotation as Related document | Status is Draft, Related shows the quotation |
| 5 | Sales/Admin | Submit PO Received for approval | Status is Pending Approval |
| 6 | Manager/Admin | Approve PO Received | Status is Approved |
| 7 | Sales/Admin | Mark issued | Status is Issued |
| 8 | Sales/Admin | Delivery / service complete | Status is Delivered / Completed |
| 9 | Sales/Admin | Create Customer Invoice and select the PO Received as Related document | Status is Draft, Related shows the PO received record |
| 10 | Sales/Admin | Submit invoice for approval | Status is Pending Approval |
| 11 | Manager/Admin | Approve invoice | Status is Approved |
| 12 | Sales/Admin | Mark issued | Status is Issued |
| 13 | Accounts/Admin | Record full payment | Status is Paid |
| 14 | Accounts/Admin | Close invoice | Status is Closed |
| 15 | Any allowed user | Preview PDF | PDF opens in a browser tab |
| 16 | Any allowed user | Download PDF | PDF downloads |
| 17 | Any allowed user | Upload attachment | Attachment appears in attachment list |
| 18 | Any allowed user | Export Customer Invoices CSV | CSV downloads |

## Workflow 4: Incoming Supplier Process

Business flow:

```text
Purchase request -> supplier quotation -> approval -> purchase order -> goods/service receipt -> supplier invoice -> invoice matching -> payment -> close
```

| Step | User | Action | Expected Result |
| --- | --- | --- | --- |
| 1 | Procurement/Admin | Create Purchase Request | Status is Draft |
| 2 | Procurement/Admin | Submit for approval | Status is Pending Approval |
| 3 | Manager/Admin | Approve Purchase Request | Status is Approved |
| 4 | Procurement/Admin | Create Supplier Quotation and select the Purchase Request as Related document | Status is Draft, Related shows the Purchase Request |
| 5 | Procurement/Admin | Submit Supplier Quotation for approval | Status is Pending Approval |
| 6 | Manager/Admin | Approve Supplier Quotation | Status is Approved |
| 7 | Procurement/Admin | Create Purchase Order and select the Supplier Quotation as Related document | Status is Draft, Related shows the Supplier Quotation |
| 8 | Procurement/Admin | Submit Purchase Order for approval | Status is Pending Approval |
| 9 | Manager/Admin | Approve Purchase Order | Status is Approved |
| 10 | Procurement/Admin | Mark issued | Status is Issued |
| 11 | Procurement/Admin | Create Goods / Service Receipt and select the Purchase Order as Related document | Status is Draft, Related shows the Purchase Order |
| 12 | Procurement/Admin | Submit receipt for approval | Status is Pending Approval |
| 13 | Manager/Admin | Approve receipt | Status is Approved |
| 14 | Procurement/Admin | Mark received | Status is Received |
| 15 | Procurement/Admin | Create Supplier Invoice and select the receipt as Related document | Status is Draft, Related shows the receipt |
| 16 | Procurement/Admin | Submit supplier invoice for approval | Status is Pending Approval |
| 17 | Manager/Admin | Approve supplier invoice | Status is Approved |
| 18 | Procurement/Admin | Mark matched | Status is Matched |
| 19 | Accounts/Admin | Record full payment | Status is Paid |
| 20 | Accounts/Admin | Close supplier invoice | Status is Closed |
| 21 | Any allowed user | Preview PDF | PDF opens in a browser tab |
| 22 | Any allowed user | Download PDF | PDF downloads |
| 23 | Any allowed user | Upload attachment | Attachment appears in attachment list |
| 24 | Any allowed user | Export Supplier Invoices CSV | CSV downloads |

## Workflow 5: Approval Rejection And Resubmission

| Step | User | Action | Expected Result |
| --- | --- | --- | --- |
| 1 | Sales/Admin | Create a quotation | Status is Draft |
| 2 | Sales/Admin | Submit for approval | Status is Pending Approval |
| 3 | Manager/Admin | Reject with a reason | Status is Rejected |
| 4 | Sales/Admin | Edit and save the document | Updated document opens |
| 5 | Sales/Admin | Submit again | Status is Pending Approval |
| 6 | Manager/Admin | Approve | Status is Approved |
| 7 | User | Check Approval History | Rejection and approval comments are visible |

## Workflow 6: Payments

| Step | User | Action | Expected Result |
| --- | --- | --- | --- |
| 1 | Accounts/Admin | Record partial customer invoice payment | Status is Part Paid |
| 2 | Accounts/Admin | Record remaining customer invoice payment | Status is Paid |
| 3 | Accounts/Admin | Record full supplier invoice payment | Status is Paid |
| 4 | Accounts/Admin | Open Payments | Payment records are listed with correct direction |

Expected payment directions:

- Customer invoice payment: Incoming
- Supplier invoice payment: Outgoing

## Workflow 7: Dashboard, Reports, And Audit Trail

| Step | User | Action | Expected Result |
| --- | --- | --- | --- |
| 1 | Admin/Manager | Open Dashboard | KPIs and workflow tracks load |
| 2 | Admin/Manager/Accounts | Open Reports | Receivables, payables, and monthly invoice totals load |
| 3 | Admin/Manager/Accounts | Export receivables, payables, and payments | CSV files download |
| 4 | Admin/Manager | Open Audit Trail | Created, approval, transition, payment, and attachment events are listed |

## Known MVP Limitations To Watch

These are expected in the current MVP and should be recorded as product improvements, not treated as test failures:

- There is no one-click Convert Quotation to PO or Convert PO to Invoice action yet.
- The tester must manually create the next document and choose the Related document.
- Supplier invoice matching is currently a manual status action, not a full 3-way matching engine.
- Approval is single-step: submit, approve, reject.
- Reports are simple CSV/table reports, not advanced analytics.

## Pass Criteria

The UAT round passes when:

- Every workflow reaches its final expected status.
- PDF preview, PDF download, and CSV downloads work.
- Attachments upload and remain visible.
- Payments update invoice status correctly.
- Role access matches the role table above.
- Audit Trail records the major workflow actions.
