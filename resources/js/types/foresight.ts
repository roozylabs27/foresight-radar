export type TimeHorizonCode = 'ST' | 'MT' | 'LT';

export interface Dimension {
    id: number;
    uuid: string;
    name: string;
    symbol: string;
}

export interface UserSummary {
    id: number;
    name: string;
    email: string;
}

export type DrivingForceStatus = 'PENDING' | 'APPROVED' | 'REJECTED' | 'CLOSED';

export interface DrivingForce {
    id: number;
    uuid: string;
    keyword: string;
    description: string;
    status: DrivingForceStatus;
    remark?: string | null;
    approved_at?: string | null;
    closed_at?: string | null;
    dimension?: Dimension;
    created_by_user?: UserSummary;
    pic_user?: UserSummary;
}

export interface CrossImpactEdge {
    source_uuid: string;
    target_uuid: string;
    score: number; // -3 to +3
    rationale?: string;
}

export interface SimulationOverride {
    uuid: string;
    impact: number; // 1 to 10
    uncertainty: number; // 1 to 10
}

export interface PropagatedShift {
    target_uuid: string;
    target_keyword: string;
    delta_impact: number;
    delta_uncertainty: number;
    final_impact: number;
    final_uncertainty: number;
    sources: string[];
}

