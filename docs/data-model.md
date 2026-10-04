# Planned data model — not implemented yet

Add business tables through Laravel migrations when their modules are built. Authentication is next. Until authenticated routes and policies exist, expose only the public API health endpoint.

- **User → farms**: each farm has a required `user_id` owner.
- **Farm → plots**: each plot has a required `farm_id`. A farmer may own many farms and plots.
- **User → crops**: farmer-specific crop definitions have `user_id`; seeds may later provide a separate shared crop catalogue.
- **Plot + crop → seasons**: each cultivation season references `plot_id` and `crop_id`, with a name, planting/start date, planned/end date and status. A plot supports multiple seasons over time. Crop and plot owners must match.
- **Season → activities, expenses, harvests**: records reference `season_id`; farm, plot, crop and owner follow the season relationships. Activities have date/type/notes; expenses have date/category/amount; harvests have date/quantity/unit.
- **Harvest → sales**: each sale references `harvest_id`, sale date, quantity, unit, unit price and total. Several sales may come from one harvest. Validate units and remaining quantity.

Use foreign keys and indexes, decimal columns for money/quantity, explicit currency/unit fields, and deliberate deletion rules. Avoid cascading deletion of financial history by default. Reports aggregate dated expenses and sales by season, farm and year; comparisons must account for acreage, currency and units. Farm-level overhead allocation can be designed with the expenses module rather than adding ambiguous nullable relationships now.

## Farmer isolation

Authenticate every future business API route. Resolve records through the authenticated user's ownership relationships and enforce Laravel policies for read/write/delete. Derive `user_id` on the server, validate that foreign IDs belong to the same farmer, and scope report queries too. CORS alone provides no access control. Add feature tests using two farmers to prove that one cannot view, update, delete or link the other's records. These are requirements for upcoming modules, not guarantees implemented by this initial scaffold.
