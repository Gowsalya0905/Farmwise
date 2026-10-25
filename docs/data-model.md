# Farmwise data model

Authentication, farms, plots, crops, seasons, activities, expenses, harvests, sales and basic reports are implemented. Farms and crops have required indexed `user_id` foreign keys with restrictive owner deletion. Plots reference farms; seasons reference plots and crops. Activities, expenses and harvests reference seasons; sales reference harvests. Authenticated queries are scoped to the current farmer and protected by policies. Owner IDs cannot be supplied by clients.

- **User → farms**: each farm has a required `user_id` owner.
- **Farm → plots**: each plot has a required `farm_id`. A farmer may own many farms and plots.
- **User → crops**: farmer-specific crop definitions have `user_id`; seeds may later provide a separate shared crop catalogue.
- **Plot + crop → seasons**: each cultivation season references `plot_id` and `crop_id`, with a name, planting/start date, planned/end date and status. A plot supports multiple seasons over time. Crop and plot owners must match.
- **Season → activities, expenses, harvests**: records reference `season_id`; farm, plot, crop and owner follow the season relationships. Activities have date/type/notes; expenses have date/category/amount; harvests have date/quantity/unit.
- **Harvest → sales**: each sale references `harvest_id`, sale date, quantity, unit, unit price and total. Several sales may come from one harvest. Validate units and remaining quantity.

Use foreign keys and indexes, decimal columns for money/quantity, explicit currency/unit fields, and deliberate deletion rules. Avoid cascading deletion of financial history by default. Reports aggregate dated expenses and sales by season, farm and year; comparisons must account for acreage, currency and units. Farm-level overhead allocation can be designed with the expenses module rather than adding ambiguous nullable relationships now.

## Record rules

Quantities use four decimal places and money uses two. Supported harvest units are kg, tonne, litre and piece; supported currencies are INR, USD, EUR and GBP. There is no automatic unit or currency conversion. Sales must use the harvest unit, cannot precede the harvest date, and cannot exceed the remaining quantity. Totals are computed server-side using scaled integers and rounded half-up to two decimals. Sale writes and harvest edits/deletes lock the harvest row inside a transaction to serialize stock changes.

Deleting a season with records or a harvest with sales returns 409. Seasons with records cannot change plot or crop. Sold harvests keep their season and unit, cannot shrink below sold stock, and must precede all their sales. Sales cannot move to another harvest. Individual records can be edited or explicitly deleted; this is not an accounting audit ledger. All relationships use restrictive foreign keys.

Reports filter by farm, season and calendar year of each record's date. Financial rows group by farm/season/year/currency, with separate currency totals; net equals recorded sales minus expenses. Harvest rows group by unit and show current plot area without calculating yield. Financial rows are present only for seasons with matching expenses or sales. No currency conversion, inventory valuation, overhead allocation or normalized yield comparison is performed.

## Farmer isolation

Authenticate every business API route. Resolve records through the authenticated user's ownership relationships and enforce Laravel policies for read/write/delete. Derive `user_id` on the server, validate that foreign IDs belong to the same farmer, and scope report queries too. CORS alone provides no access control. The farms feature tests use two farmers to prove read/update/delete isolation and rejected ownership assignment. Future modules must add tests for foreign relationship IDs before exposing those modules; the farms tests do not guarantee isolation for unimplemented tables.
