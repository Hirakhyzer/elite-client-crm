export const GOLD = "#f4af00";
export const GOLD_DARK = "#c68f00";
export const GOLD_LIGHT = "#fff8e1";

export const PIPELINE_STAGES = ["Outreach", "Discovery", "Proposal", "Negotiation", "Closed Won", "Closed Lost"];
export const INDUSTRIES = ["Tech", "Marketing", "Finance", "Education", "Logistics", "Health", "Retail"];
export const TIERS = ["Elite", "Gold", "Silver", "Bronze"];
export const STATUSES = ["Active", "Lead", "Churned", "Inactive"];
export const ACTIVITY_TYPES = ["Call", "Email", "Meeting", "Note"];

const client = (id, name, industry, status, tier, deal, pipeline, score, tags) => ({
  id,
  name,
  contact: "Project Contact",
  email: `contact${id}@example.com`,
  phone: "",
  company: name,
  industry,
  status,
  tier,
  deal,
  pipeline,
  tags,
  score,
  assigned: "Hira",
  joined: "2026-05-01",
  country: "Global",
  lastContact: "2026-06-15",
  notes: "Demo client record for the Elite Client CRM.",
  tasks: [{ text: "Schedule follow-up", done: false }],
  activity: [{ type: "Note", note: "Client record created", date: "2026-06-15" }]
});

export const initialStore = {
  clients: [
    client(1, "Northstar Studio", "Tech", "Active", "Elite", 48000, "Closed Won", 92, ["SaaS", "AI"]),
    client(2, "Nexus Digital", "Marketing", "Active", "Gold", 22000, "Proposal", 74, ["SEO", "Branding"]),
    client(3, "Summit Group", "Finance", "Lead", "Silver", 9500, "Negotiation", 61, ["ERP", "Automation"]),
    client(4, "CloudPeak Labs", "Tech", "Churned", "Gold", 18000, "Closed Lost", 38, ["AI", "Cloud"]),
    client(5, "BrightPath Education", "Education", "Active", "Elite", 55000, "Closed Won", 96, ["LMS", "SaaS"]),
    client(6, "Orion Logistics", "Logistics", "Lead", "Silver", 7200, "Outreach", 45, ["ERP", "Supply Chain"])
  ],
  invoices: [
    { id: "INV-001", client: "BrightPath Education", amount: 55000, status: "Paid", due: "2026-05-01", issued: "2026-04-01" },
    { id: "INV-002", client: "Northstar Studio", amount: 48000, status: "Paid", due: "2026-05-15", issued: "2026-04-15" },
    { id: "INV-003", client: "Nexus Digital", amount: 22000, status: "Pending", due: "2026-07-01", issued: "2026-06-01" },
    { id: "INV-004", client: "Summit Group", amount: 9500, status: "Overdue", due: "2026-05-20", issued: "2026-04-20" }
  ],
  events: [
    { id: 1, title: "Renewal review", date: "2026-06-22", time: "10:00", client: "BrightPath Education", type: "Call" },
    { id: 2, title: "Strategy session", date: "2026-06-24", time: "14:00", client: "Northstar Studio", type: "Meeting" },
    { id: 3, title: "Proposal review", date: "2026-06-26", time: "11:00", client: "Nexus Digital", type: "Meeting" },
    { id: 4, title: "Discovery call", date: "2026-06-29", time: "09:00", client: "Orion Logistics", type: "Call" }
  ],
  emails: [],
  goals: [
    { label: "Monthly Revenue", current: 74000, target: 100000, prefix: "$" },
    { label: "New Clients", current: 3, target: 5, prefix: "" },
    { label: "Active Deals", current: 4, target: 6, prefix: "" }
  ]
};
