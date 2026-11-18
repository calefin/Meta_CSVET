export interface User {
  id: string
  email: string
  name: string
  role: 'admin' | 'vet' | 'breeder'
  locale: string
  is_active: boolean
  plan_id?: string
}

export interface Plan {
  id: string
  name: 'free' | 'basic' | 'pro' | 'premium'
  max_birds: number
  price_monthly: number
  price_yearly: number
}

export interface Species {
  id: string
  common_name: string
  scientific_name: string
  category: string
  avg_lifespan_years?: number
  incubation_days?: number
}

export interface Bird {
  id: string
  user_id: string
  name: string
  ring_number?: string
  microchip?: string
  species_id?: string
  breed_id?: string
  sex: 'M' | 'F' | 'unknown'
  birth_date?: string
  acquisition_date?: string
  acquisition_type?: string
  color?: string
  plumage_description?: string
  weight_grams?: number
  father_id?: string
  mother_id?: string
  photo_url?: string
  status: 'active' | 'deceased' | 'sold' | 'transferred'
  notes?: string
  created_at: string
  species?: Species
}

export interface Cage {
  id: string
  user_id: string
  name: string
  location?: string
  notes?: string
  is_active: boolean
  created_at: string
}

export interface Breeding {
  id: string
  user_id: string
  male_id: string
  female_id: string
  cage_id?: string
  pairing_date: string
  separation_date?: string
  nest_type?: string
  nest_number?: string
  status: 'paired' | 'incubating' | 'hatched' | 'failed' | 'completed'
  notes?: string
  created_at: string
}

export interface Clutch {
  id: string
  breeding_id: string
  lay_date: string
  egg_count: number
  fertile_count?: number
  hatched_count?: number
  candling_date?: string
  candling_notes?: string
  created_at: string
}

export interface Offspring {
  id: string
  clutch_id: string
  bird_id?: string
  hatch_date?: string
  hatch_weight_g?: number
  ring_date?: string
  ring_number?: string
  notes?: string
}

export interface HealthRecord {
  id: string
  bird_id: string
  user_id: string
  date: string
  weight_grams?: number
  temperature_celsius?: number
  droppings_aspect?: string
  behavior_notes?: string
  symptoms_json?: string[]
  photos_urls?: string[]
  severity: 'normal' | 'attention' | 'urgent'
  created_at: string
}

export interface VetConsultRequest {
  id: string
  health_record_id?: string
  bird_id: string
  requester_user_id: string
  assigned_vet_id?: string
  status: 'pending' | 'in_review' | 'responded' | 'closed'
  priority: 'low' | 'medium' | 'high' | 'emergency'
  description?: string
  response_text?: string
  response_date?: string
  created_at: string
}

export interface VaccineProduct {
  id: string
  name_pt: string
  name_en: string
  route?: string
  interval_days?: number
}

export interface VaccinationRecord {
  id: string
  bird_id: string
  vaccine_product_id: string
  application_date: string
  next_dose_date?: string
  dose_number: number
  reaction_observed: boolean
}

export interface Race {
  id: string
  name: string
  date: string
  start_location?: string
  distance_km?: number
  organizer?: string
  category?: string
}

export interface RaceResult {
  id: string
  race_id: string
  bird_id: string
  flight_time_seconds?: number
  velocity_mpm?: number
  position_overall?: number
  arrival_time?: string
}

export interface GeneticLocus {
  id: string
  species_id: string
  symbol: string
  name_pt: string
  name_en: string
  inheritance_mode: string
  is_multi_allele: boolean
  alleles_json?: { symbol: string; name_pt: string; rank: number }[]
  display_order: number
}

export interface GeneticResultItem {
  sex: string
  phenotype: string[]
  splits: string[]
  genotype_code: string
  probability: number
  visual_description: string
}

export interface TokenResponse {
  access_token: string
  refresh_token: string
  token_type: string
}

export interface DashboardAlert {
  type: 'vaccine_overdue' | 'vaccine_upcoming' | 'health_severity' | 'vet_pending' | 'medication_withdrawal'
  severity: 'urgent' | 'attention' | 'info'
  bird_id?: string
  bird_name?: string
  message: string
  date: string
}

export interface DashboardActiveBreeding {
  id: string
  male_name?: string
  female_name?: string
  cage_name?: string
  status: string
  pairing_date: string
  latest_clutch_lay_date?: string
  expected_hatch_date?: string
}

export interface DashboardSummary {
  stats: {
    active_birds: number
    active_pairs: number
    cages_free: number
    cages_total: number
    pending_vet_consults: number
  }
  species_breakdown: { species_name: string; count: number }[]
  active_breedings: DashboardActiveBreeding[]
  alerts: DashboardAlert[]
}

export interface EmailTemplate {
  id: string
  name: string
  subject: string
  html_body: string
  created_by: string
  created_at: string
  updated_at?: string
}

export interface SegmentFilters {
  roles?: string[]
  plan_names?: string[]
  locales?: string[]
  signup_after?: string
  signup_before?: string
  species_ids?: string[]
}

export interface Campaign {
  id: string
  name: string
  template_id?: string
  subject: string
  html_body: string
  from_address: string
  segment_filters_json?: SegmentFilters
  status: 'draft' | 'sending' | 'sent' | 'failed'
  total_recipients: number
  total_sent: number
  total_failed: number
  created_by: string
  created_at: string
  sent_at?: string
}
