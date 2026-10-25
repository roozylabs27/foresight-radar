import { useEffect, useId, useMemo, useRef, useState } from "react";
import { Head, Link } from "@inertiajs/react";
import axios from "axios";
import { toast } from "sonner";
import {
    ActivityIcon,
    ArrowRightIcon,
    CheckCircle2Icon,
    CircleDotIcon,
    ExternalLinkIcon,
    FileTextIcon,
    FilterIcon,
    Maximize2Icon,
    NetworkIcon,
    RotateCcwIcon,
    SearchIcon,
    SlidersHorizontalIcon,
    ZapIcon,
    ZoomInIcon,
    ZoomOutIcon,
} from "lucide-react";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import DimensionChip, { dimensionColor } from "@/Components/DimensionChip";
import Panel from "@/Components/Panel";
import { statusBadge } from "@/Components/StatusBadge";
import { useTheme } from "@/Components/ThemeProvider";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Skeleton } from "@/Components/ui/skeleton";
import { ToggleGroup, ToggleGroupItem } from "@/Components/ui/toggle-group";
import { cn } from "@/lib/utils";

const DIMENSION_COLORS_DARK = {
    Technology: "#2fd1c2",
    Economic: "#e3b23c",
    Economy: "#e3b23c",
    Political: "#a98bff",
    Regulation: "#a98bff",
    Social: "#f07ab3",
    Environmental: "#7bd36a",
    Ecology: "#7bd36a",
    Competitor: "#ff7a6e",
    Customer: "#f07ab3",
    Supplier: "#c9a36b",
    Substitute: "#ff9d4d",
    General: "#a3b2c9",
    Source: "#8797b0",
};

const DIMENSION_COLORS_LIGHT = {
    Technology: "#0b8f8a",
    Economic: "#b07d00",
    Economy: "#b07d00",
    Political: "#6a4cc0",
    Regulation: "#6a4cc0",
    Social: "#c0397a",
    Environmental: "#3d8a2c",
    Ecology: "#3d8a2c",
    Competitor: "#d1433a",
    Customer: "#c0397a",
    Supplier: "#7a5a2e",
    Substitute: "#e0741c",
    General: "#4a5872",
    Source: "#5f6d84",
};

const THEME_CANVAS_DARK = {
    background: "#08101e",
    gridDot: "rgba(233, 238, 247, 0.08)",
    linkDefault: "rgba(163, 178, 201, 0.28)",
    linkSignalToDf: "rgba(163, 178, 201, 0.38)",
    linkSourceToSignal: "rgba(135, 151, 176, 0.22)",
    linkActive: "#6aa5ff",
    hubFill: "#0f1a2d",
    sourceFill: "#16233a",
    sourceBorder: "#8797b0",
    signalBorder: "#08101e",
    labelPillBg: "rgba(8, 16, 30, 0.88)",
    labelPillBorder: "rgba(34, 49, 75, 0.85)",
    labelText: "#e9eef7",
    labelTextActive: "#6aa5ff",
};

const THEME_CANVAS_LIGHT = {
    background: "#f5f7fb",
    gridDot: "rgba(11, 27, 51, 0.07)",
    linkDefault: "rgba(74, 88, 114, 0.22)",
    linkSignalToDf: "rgba(74, 88, 114, 0.32)",
    linkSourceToSignal: "rgba(95, 109, 132, 0.18)",
    linkActive: "#1a5fd6",
    hubFill: "#ffffff",
    sourceFill: "#eef2f8",
    sourceBorder: "#7b889c",
    signalBorder: "#ffffff",
    labelPillBg: "rgba(255, 255, 255, 0.94)",
    labelPillBorder: "rgba(225, 231, 239, 0.95)",
    labelText: "#0b1b33",
    labelTextActive: "#1a5fd6",
};

function getNodeColor(node, isDark = true) {
    const palette = isDark ? DIMENSION_COLORS_DARK : DIMENSION_COLORS_LIGHT;
    if (node.type === "source") return palette.Source;
    return palette[node.dimension] ?? palette.General;
}

export default function KnowledgeGraph({ auth, title }) {
    const { theme } = useTheme();
    const isDark = theme === "dark";
    const isDarkRef = useRef(isDark);

    useEffect(() => {
        isDarkRef.current = isDark;
    }, [isDark]);

    const [graphData, setGraphData] = useState(null);
    const [loading, setLoading] = useState(true);

    // Filters
    const [searchQuery, setSearchQuery] = useState("");
    const [selectedDimension, setSelectedDimension] = useState("all");
    const [signalStatusFilter, setSignalStatusFilter] = useState("all"); // 'all' | 'ACCEPTED' | 'PENDING'
    const [showSources, setShowSources] = useState(true);

    // Interaction state
    const [activeNode, setActiveNode] = useState(null);
    const [hoveredNode, setHoveredNode] = useState(null);

    const canvasRef = useRef(null);
    const simulationRef = useRef({
        nodes: [],
        links: [],
        transform: { x: 0, y: 0, k: 1 },
        isDragging: false,
        draggedNode: null,
        panStart: { x: 0, y: 0 },
        animId: null,
    });

    // 1. Fetch graph dataset
    useEffect(() => {
        let isMounted = true;
        setLoading(true);
        axios
            .get("/visualization/knowledge-graph/data")
            .then((res) => {
                if (!isMounted) return;
                setGraphData(res.data);
            })
            .catch((err) => {
                if (!isMounted) return;
                toast.error("Gagal memuat dataset knowledge graph.", {
                    description: err.message,
                });
            })
            .finally(() => {
                if (isMounted) setLoading(false);
            });

        return () => {
            isMounted = false;
        };
    }, []);

    // 2. Filter nodes and links based on UI controls
    const filteredGraph = useMemo(() => {
        if (!graphData) return { nodes: [], links: [] };

        const { nodes, links } = graphData;

        const allowedNodeIds = new Set();
        const filteredNodes = nodes.filter((node) => {
            if (node.type === "source" && !showSources) return false;

            if (selectedDimension !== "all" && node.type !== "source") {
                if (node.dimension !== selectedDimension) return false;
            }

            if (node.type === "signal" && signalStatusFilter !== "all") {
                if (node.review_status !== signalStatusFilter) return false;
            }

            if (searchQuery.trim()) {
                const q = searchQuery.toLowerCase();
                const matchLabel = (node.label ?? "").toLowerCase().includes(q);
                const matchQuote = (node.quote ?? "").toLowerCase().includes(q);
                if (!matchLabel && !matchQuote) return false;
            }

            allowedNodeIds.add(node.id);
            return true;
        });

        const filteredLinks = links.filter(
            (link) => allowedNodeIds.has(link.source) && allowedNodeIds.has(link.target)
        );

        return { nodes: filteredNodes, links: filteredLinks };
    }, [graphData, selectedDimension, signalStatusFilter, showSources, searchQuery]);

    // 3. Initialize & Run Physics Simulation
    useEffect(() => {
        const canvas = canvasRef.current;
        if (!canvas || !filteredGraph.nodes.length) return;

        const ctx = canvas.getContext("2d");
        const sim = simulationRef.current;

        // Resize canvas to match display size with DPR
        const width = canvas.parentElement.clientWidth || 900;
        const height = Math.max(620, window.innerHeight * 0.65);
        const dpr = window.devicePixelRatio || 1;

        canvas.width = width * dpr;
        canvas.height = height * dpr;
        canvas.style.width = `${width}px`;
        canvas.style.height = `${height}px`;

        // Preserve previous positions if available to prevent jitter
        const prevPos = new Map(sim.nodes.map((n) => [n.id, { x: n.x, y: n.y, vx: n.vx, vy: n.vy }]));

        // Initialize positions in radial clusters
        const centerX = width / 2;
        const centerY = height / 2;

        const simNodes = filteredGraph.nodes.map((n, i) => {
            const existing = prevPos.get(n.id);
            if (existing) {
                return { ...n, ...existing };
            }
            // Seed layout: Driving Forces spread out, signals orbit their parent
            const angle = (2 * Math.PI * i) / filteredGraph.nodes.length;
            const dist = n.type === "driving_force" ? 180 + (i % 3) * 40 : 260 + (i % 5) * 30;
            return {
                ...n,
                x: centerX + Math.cos(angle) * dist + (Math.random() - 0.5) * 30,
                y: centerY + Math.sin(angle) * dist + (Math.random() - 0.5) * 30,
                vx: 0,
                vy: 0,
            };
        });

        const nodeMap = new Map(simNodes.map((n) => [n.id, n]));

        const simLinks = filteredGraph.links
            .map((l) => ({
                ...l,
                sourceNode: nodeMap.get(l.source),
                targetNode: nodeMap.get(l.target),
            }))
            .filter((l) => l.sourceNode && l.targetNode);

        sim.nodes = simNodes;
        sim.links = simLinks;

        let alpha = 1.0; // Cooling factor
        const minAlpha = 0.005;

        function tick() {
            if (alpha > minAlpha) {
                alpha *= 0.985;

                // 1. Center gravitational pull
                for (let i = 0; i < simNodes.length; i++) {
                    const n = simNodes[i];
                    if (n === sim.draggedNode) continue;
                    const dx = centerX - n.x;
                    const dy = centerY - n.y;
                    const gravity = n.type === "driving_force" ? 0.003 : 0.0015;
                    n.vx += dx * gravity * alpha;
                    n.vy += dy * gravity * alpha;
                }

                // 2. Repulsion between all nodes (Coulomb's law)
                for (let i = 0; i < simNodes.length; i++) {
                    const a = simNodes[i];
                    for (let j = i + 1; j < simNodes.length; j++) {
                        const b = simNodes[j];
                        let dx = b.x - a.x;
                        let dy = b.y - a.y;
                        let dist = Math.hypot(dx, dy) || 1;

                        const minDist = (a.val + b.val) * 2.8;
                        if (dist < minDist * 2.5) {
                            const force = ((minDist * minDist) / (dist * dist)) * 0.35 * alpha;
                            const fx = (dx / dist) * force;
                            const fy = (dy / dist) * force;

                            if (a !== sim.draggedNode) {
                                a.vx -= fx;
                                a.vy -= fy;
                            }
                            if (b !== sim.draggedNode) {
                                b.vx += fx;
                                b.vy += fy;
                            }
                        }
                    }
                }

                // 3. Spring attraction along links (Hooke's law)
                for (let i = 0; i < simLinks.length; i++) {
                    const link = simLinks[i];
                    const s = link.sourceNode;
                    const t = link.targetNode;

                    let dx = t.x - s.x;
                    let dy = t.y - s.y;
                    let dist = Math.hypot(dx, dy) || 1;

                    // Desired target distance
                    const targetDist = link.type === "signal_to_df" ? 65 : 110;
                    const displacement = dist - targetDist;
                    const strength = (link.type === "signal_to_df" ? 0.08 : 0.04) * alpha;

                    const fx = (dx / dist) * displacement * strength;
                    const fy = (dy / dist) * displacement * strength;

                    if (s !== sim.draggedNode) {
                        s.vx += fx;
                        s.vy += fy;
                    }
                    if (t !== sim.draggedNode) {
                        t.vx += fx;
                        t.vy += fy;
                    }
                }

                // 4. Update velocity & position with damping
                const damping = 0.72;
                for (let i = 0; i < simNodes.length; i++) {
                    const n = simNodes[i];
                    if (n === sim.draggedNode) continue;
                    n.vx *= damping;
                    n.vy *= damping;
                    n.x += n.vx;
                    n.y += n.vy;
                }
            }

            const currentDark = isDarkRef.current;
            const themeColors = currentDark ? THEME_CANVAS_DARK : THEME_CANVAS_LIGHT;

            // Render Frame
            ctx.save();
            ctx.scale(dpr, dpr);

            // Fill canvas with theme background
            ctx.fillStyle = themeColors.background;
            ctx.fillRect(0, 0, width, height);

            // Apply Pan & Zoom
            const { x: panX, y: panY, k: zoom } = sim.transform;
            ctx.translate(panX, panY);
            ctx.scale(zoom, zoom);

            // Draw subtle blueprint grid dots in world coordinates
            const gridSize = 64;
            const worldLeft = -panX / zoom;
            const worldTop = -panY / zoom;
            const worldRight = (width - panX) / zoom;
            const worldBottom = (height - panY) / zoom;

            const startX = Math.floor(worldLeft / gridSize) * gridSize;
            const startY = Math.floor(worldTop / gridSize) * gridSize;

            ctx.fillStyle = themeColors.gridDot;
            for (let gx = startX; gx < worldRight; gx += gridSize) {
                for (let gy = startY; gy < worldBottom; gy += gridSize) {
                    ctx.beginPath();
                    ctx.arc(gx, gy, 1.1, 0, Math.PI * 2);
                    ctx.fill();
                }
            }

            // Connected set for highlighting
            const activeId = activeNode?.id || hoveredNode?.id;
            let connectedIds = null;
            if (activeId) {
                connectedIds = new Set([activeId]);
                for (const l of simLinks) {
                    if (l.sourceNode.id === activeId) connectedIds.add(l.targetNode.id);
                    if (l.targetNode.id === activeId) connectedIds.add(l.sourceNode.id);
                }
            }

            // 1. Draw Links
            for (let i = 0; i < simLinks.length; i++) {
                const l = simLinks[i];
                const s = l.sourceNode;
                const t = l.targetNode;

                let isConnected = false;
                let opacity = currentDark ? 0.32 : 0.42;
                let lineWidth = 1;

                if (connectedIds) {
                    if (connectedIds.has(s.id) && connectedIds.has(t.id)) {
                        isConnected = true;
                        opacity = 0.95;
                        lineWidth = 2.4;
                    } else {
                        opacity = currentDark ? 0.05 : 0.08;
                    }
                }

                ctx.beginPath();
                ctx.moveTo(s.x, s.y);
                ctx.lineTo(t.x, t.y);
                ctx.strokeStyle = isConnected
                    ? themeColors.linkActive
                    : l.type === "signal_to_df"
                      ? themeColors.linkSignalToDf
                      : themeColors.linkSourceToSignal;
                ctx.globalAlpha = opacity;
                ctx.lineWidth = lineWidth;
                ctx.stroke();
                ctx.globalAlpha = 1;
            }

            // 2. Draw Nodes
            for (let i = 0; i < simNodes.length; i++) {
                const n = simNodes[i];
                const isSelected = activeNode?.id === n.id;
                const isHovered = hoveredNode?.id === n.id;
                const isHighlighted = !connectedIds || connectedIds.has(n.id);

                const baseRadius = n.val;
                const radius = isSelected || isHovered ? baseRadius * 1.25 : baseRadius;
                const color = getNodeColor(n, currentDark);

                ctx.globalAlpha = isHighlighted ? 1 : 0.15;

                // Outer halo for highlighted/selected
                if (isSelected || isHovered) {
                    ctx.beginPath();
                    ctx.arc(n.x, n.y, radius + 6, 0, Math.PI * 2);
                    ctx.fillStyle = color;
                    ctx.globalAlpha = currentDark ? 0.28 : 0.22;
                    ctx.fill();
                    ctx.globalAlpha = isHighlighted ? 1 : 0.15;
                }

                // Node Body
                ctx.beginPath();
                ctx.arc(n.x, n.y, radius, 0, Math.PI * 2);

                if (n.type === "driving_force") {
                    // Hub: card background with colored ring
                    ctx.fillStyle = themeColors.hubFill;
                    ctx.fill();
                    ctx.lineWidth = 3;
                    ctx.strokeStyle = color;
                    ctx.stroke();
                } else if (n.type === "source") {
                    ctx.fillStyle = themeColors.sourceFill;
                    ctx.fill();
                    ctx.lineWidth = 2;
                    ctx.strokeStyle = themeColors.sourceBorder;
                    ctx.stroke();
                } else {
                    // Signal: filled circle
                    ctx.fillStyle = color;
                    ctx.fill();
                    ctx.lineWidth = 1.5;
                    ctx.strokeStyle = themeColors.signalBorder;
                    ctx.stroke();
                }

                // Node Text Label
                if (n.type === "driving_force" || isSelected || isHovered || zoom > 1.2) {
                    ctx.font = n.type === "driving_force" ? "bold 11px system-ui, sans-serif" : "10px system-ui, sans-serif";
                    ctx.textAlign = "center";
                    ctx.textBaseline = "middle";

                    // Background pill for label
                    const label = n.label.length > 22 ? n.label.slice(0, 20) + "…" : n.label;
                    const textWidth = ctx.measureText(label).width;
                    const textY = n.y + radius + 11;

                    const pillX = n.x - textWidth / 2 - 5;
                    const pillY = textY - 7;
                    const pillW = textWidth + 10;
                    const pillH = 15;

                    ctx.fillStyle = themeColors.labelPillBg;
                    ctx.strokeStyle = themeColors.labelPillBorder;
                    ctx.lineWidth = 1;

                    ctx.beginPath();
                    if (typeof ctx.roundRect === "function") {
                        ctx.roundRect(pillX, pillY, pillW, pillH, 3);
                    } else {
                        ctx.rect(pillX, pillY, pillW, pillH);
                    }
                    ctx.fill();
                    ctx.stroke();

                    ctx.fillStyle = isSelected ? themeColors.labelTextActive : themeColors.labelText;
                    ctx.fillText(label, n.x, textY);
                }

                ctx.globalAlpha = 1;
            }

            ctx.restore();
            sim.animId = requestAnimationFrame(tick);
        }

        sim.animId = requestAnimationFrame(tick);

        return () => {
            if (sim.animId) cancelAnimationFrame(sim.animId);
        };
    }, [filteredGraph, activeNode, hoveredNode]);

    // Mouse & Touch events on Canvas
    const handleMouseDown = (e) => {
        const canvas = canvasRef.current;
        if (!canvas) return;
        const rect = canvas.getBoundingClientRect();
        const sim = simulationRef.current;

        const mouseX = (e.clientX - rect.left - sim.transform.x) / sim.transform.k;
        const mouseY = (e.clientY - rect.top - sim.transform.y) / sim.transform.k;

        // Check if clicked a node
        let clicked = null;
        for (let i = sim.nodes.length - 1; i >= 0; i--) {
            const n = sim.nodes[i];
            const dist = Math.hypot(n.x - mouseX, n.y - mouseY);
            if (dist <= n.val + 4) {
                clicked = n;
                break;
            }
        }

        if (clicked) {
            sim.isDragging = true;
            sim.draggedNode = clicked;
            setActiveNode(clicked);
        } else {
            sim.isDragging = true;
            sim.panStart = { x: e.clientX - sim.transform.x, y: e.clientY - sim.transform.y };
        }
    };

    const handleMouseMove = (e) => {
        const canvas = canvasRef.current;
        if (!canvas) return;
        const rect = canvas.getBoundingClientRect();
        const sim = simulationRef.current;

        const mouseX = (e.clientX - rect.left - sim.transform.x) / sim.transform.k;
        const mouseY = (e.clientY - rect.top - sim.transform.y) / sim.transform.k;

        if (sim.isDragging) {
            if (sim.draggedNode) {
                sim.draggedNode.x = mouseX;
                sim.draggedNode.y = mouseY;
                sim.draggedNode.vx = 0;
                sim.draggedNode.vy = 0;
            } else {
                sim.transform.x = e.clientX - sim.panStart.x;
                sim.transform.y = e.clientY - sim.panStart.y;
            }
        } else {
            // Hover check
            let hovered = null;
            for (let i = sim.nodes.length - 1; i >= 0; i--) {
                const n = sim.nodes[i];
                const dist = Math.hypot(n.x - mouseX, n.y - mouseY);
                if (dist <= n.val + 4) {
                    hovered = n;
                    break;
                }
            }
            if (hovered?.id !== hoveredNode?.id) {
                setHoveredNode(hovered);
                canvas.style.cursor = hovered ? "pointer" : "default";
            }
        }
    };

    const handleMouseUp = () => {
        const sim = simulationRef.current;
        sim.isDragging = false;
        sim.draggedNode = null;
    };

    // Wheel zoom with non-passive event listener
    useEffect(() => {
        const canvas = canvasRef.current;
        if (!canvas) return;

        const onWheel = (e) => {
            e.preventDefault();
            const sim = simulationRef.current;
            const zoomFactor = e.deltaY < 0 ? 1.12 : 0.89;
            const newK = Math.max(0.35, Math.min(3.5, sim.transform.k * zoomFactor));

            const rect = canvas.getBoundingClientRect();
            const mx = e.clientX - rect.left;
            const my = e.clientY - rect.top;

            sim.transform.x = mx - (mx - sim.transform.x) * (newK / sim.transform.k);
            sim.transform.y = my - (my - sim.transform.y) * (newK / sim.transform.k);
            sim.transform.k = newK;
        };

        canvas.addEventListener("wheel", onWheel, { passive: false });
        return () => {
            canvas.removeEventListener("wheel", onWheel);
        };
    }, []);

    const zoomBy = (factor) => {
        const sim = simulationRef.current;
        const canvas = canvasRef.current;
        if (!canvas) return;
        const newK = Math.max(0.35, Math.min(3.5, sim.transform.k * factor));
        const cx = canvas.clientWidth / 2;
        const cy = canvas.clientHeight / 2;
        sim.transform.x = cx - (cx - sim.transform.x) * (newK / sim.transform.k);
        sim.transform.y = cy - (cy - sim.transform.y) * (newK / sim.transform.k);
        sim.transform.k = newK;
    };

    const resetView = () => {
        const sim = simulationRef.current;
        sim.transform = { x: 0, y: 0, k: 1 };
        setActiveNode(null);
    };

    return (
        <AuthenticatedLayout
            auth={auth}
            header={
                <div className="flex min-w-0 items-center gap-2.5">
                    <NetworkIcon aria-hidden="true" className="text-lg text-primary" />
                    <h1 className="m-0 truncate text-lg font-bold text-foreground">{title}</h1>
                    <span className={cn(statusBadge(), "hidden bg-accent text-primary sm:inline-flex")}>
                        Topologi Jejaring Sinyal &amp; Driving Force
                    </span>
                </div>
            }
        >
            <Head title={title} />

            <main className="flex flex-1 flex-col gap-4 p-4 lg:px-6 lg:py-5">
                {loading || !graphData ? (
                    <div className="flex flex-col gap-4">
                        <Skeleton className="h-16 w-full" />
                        <Skeleton className="h-[600px] w-full" />
                    </div>
                ) : (
                    <>
                        {/* Summary & Filters Bar */}
                        <div className="flex flex-col gap-3 rounded-lg border border-border bg-card p-4 shadow-xs">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div className="flex flex-wrap items-center gap-2">
                                    <div className="relative w-64">
                                        <SearchIcon className="absolute left-2.5 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
                                        <Input
                                            type="text"
                                            id="knowledge-graph-search"
                                            name="knowledge-graph-search"
                                            placeholder="Cari sinyal atau force…"
                                            value={searchQuery}
                                            onChange={(e) => setSearchQuery(e.target.value)}
                                            className="h-8 pl-8 text-xs bg-background text-foreground"
                                        />
                                    </div>

                                    {/* Dimensi Selector */}
                                    <select
                                        id="knowledge-graph-dimension"
                                        name="knowledge-graph-dimension"
                                        value={selectedDimension}
                                        onChange={(e) => setSelectedDimension(e.target.value)}
                                        className="h-8 rounded-md border border-input bg-background px-2.5 text-xs text-foreground outline-none focus:ring-2 focus:ring-ring"
                                    >
                                        <option value="all">Semua Dimensi</option>
                                        {graphData.dimensions.map((dim) => (
                                            <option key={dim} value={dim}>
                                                {dim}
                                            </option>
                                        ))}
                                    </select>

                                    {/* Status Sinyal */}
                                    <ToggleGroup
                                        type="single"
                                        variant="outline"
                                        size="sm"
                                        value={signalStatusFilter}
                                        onValueChange={(val) => val && setSignalStatusFilter(val)}
                                        className="bg-background"
                                    >
                                        <ToggleGroupItem value="all" className="h-8 px-2.5 text-xs">
                                            Semua Status
                                        </ToggleGroupItem>
                                        <ToggleGroupItem value="ACCEPTED" className="h-8 px-2.5 text-xs">
                                            Accepted
                                        </ToggleGroupItem>
                                        <ToggleGroupItem value="PENDING" className="h-8 px-2.5 text-xs">
                                            Pending
                                        </ToggleGroupItem>
                                    </ToggleGroup>

                                    {/* Toggle Dokumen Sumber */}
                                    <Button
                                        type="button"
                                        variant={showSources ? "default" : "outline"}
                                        size="sm"
                                        className="h-8 text-xs gap-1.5"
                                        onClick={() => setShowSources(!showSources)}
                                    >
                                        <FileTextIcon className="size-3.5" />
                                        {showSources ? "Sertakan Dokumen" : "Sembunyikan Dokumen"}
                                    </Button>
                                </div>

                                {/* Graph Statistics Pill */}
                                <div className="flex items-center gap-3 text-xs text-muted-foreground">
                                    <span className="font-semibold text-foreground">
                                        {filteredGraph.nodes.length} Entitas ({graphData.stats.driving_forces_count} Force, {graphData.stats.signals_count} Sinyal)
                                    </span>
                                    <span>·</span>
                                    <span>{filteredGraph.links.length} Relasi</span>
                                </div>
                            </div>
                        </div>

                        {/* Graph Workspace with Canvas & Floating Inspector */}
                        <div className="relative flex flex-col overflow-hidden rounded-lg border border-border bg-background shadow-inner transition-colors duration-200">
                            {/* Canvas Toolbar Controls */}
                            <div className="absolute left-3 top-3 z-10 flex flex-col gap-1.5 rounded-md border border-border/70 bg-card/90 p-1 shadow-md backdrop-blur-md">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="size-7 text-foreground hover:bg-muted"
                                    title="Perbesar (Zoom In)"
                                    onClick={() => zoomBy(1.2)}
                                >
                                    <ZoomInIcon className="size-4" />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="size-7 text-foreground hover:bg-muted"
                                    title="Perkecil (Zoom Out)"
                                    onClick={() => zoomBy(0.8)}
                                >
                                    <ZoomOutIcon className="size-4" />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="size-7 text-foreground hover:bg-muted"
                                    title="Reset Tampilan"
                                    onClick={resetView}
                                >
                                    <RotateCcwIcon className="size-4" />
                                </Button>
                            </div>

                            {/* Canvas Simulation Area */}
                            <canvas
                                ref={canvasRef}
                                onMouseDown={handleMouseDown}
                                onMouseMove={handleMouseMove}
                                onMouseUp={handleMouseUp}
                                className="block w-full touch-none"
                            />

                            {/* Floating Inspector HUD (Side or Bottom Card) */}
                            {activeNode && (
                                <div className="absolute right-3 top-3 z-20 max-w-sm w-full rounded-lg border border-border bg-card/95 p-4 shadow-xl backdrop-blur-md transition-all">
                                    <div className="flex items-start justify-between gap-2 border-b border-border pb-2.5">
                                        <div className="flex items-center gap-2">
                                            <span
                                                className="size-3.5 shrink-0 rounded-full"
                                                style={{ backgroundColor: getNodeColor(activeNode, isDark) }}
                                            />
                                            <span className="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                                                {activeNode.type === "driving_force"
                                                    ? "Driving Force"
                                                    : activeNode.type === "source"
                                                      ? "Dokumen Sumber"
                                                      : "Sinyal Kandidat"}
                                            </span>
                                        </div>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className="h-6 px-1.5 text-xs text-muted-foreground hover:text-foreground"
                                            onClick={() => setActiveNode(null)}
                                        >
                                            ✕
                                        </Button>
                                    </div>

                                    <div className="mt-3 flex flex-col gap-2.5 text-xs">
                                        <h4 className="font-bold text-sm text-foreground leading-tight">
                                            {activeNode.label}
                                        </h4>

                                        {activeNode.type === "signal" && (
                                            <>
                                                {activeNode.quote && (
                                                    <blockquote className="border-l-2 border-primary/60 pl-2.5 italic text-muted-foreground my-1">
                                                        "{activeNode.quote}"
                                                    </blockquote>
                                                )}
                                                <div className="grid grid-cols-2 gap-2 text-[11px] text-muted-foreground pt-1">
                                                    <div>
                                                        Dimensi: <b className="text-foreground">{activeNode.dimension}</b>
                                                    </div>
                                                    <div>
                                                        Confidence:{" "}
                                                        <b className="text-foreground">
                                                             {Math.round((activeNode.confidence || 0.8) * 100)}%
                                                        </b>
                                                    </div>
                                                    <div>
                                                        Impact: <b className="text-foreground">{activeNode.impact}/10</b>
                                                    </div>
                                                    <div>
                                                        Uncertainty: <b className="text-foreground">{activeNode.uncertainty}/10</b>
                                                    </div>
                                                </div>
                                                {activeNode.source_title && (
                                                    <p className="m-0 text-[11px] text-muted-foreground">
                                                        Sumber: <span className="font-medium text-foreground">{activeNode.source_title}</span>
                                                    </p>
                                                )}
                                                <div className="mt-2 pt-2 border-t border-border flex items-center justify-between">
                                                    <span className={cn(statusBadge(), activeNode.review_status === "ACCEPTED" ? "bg-emerald-500/15 text-emerald-600 dark:text-emerald-400" : "bg-warning/15 text-warning")}>
                                                        {activeNode.review_status}
                                                    </span>
                                                    <Link
                                                        href={route("signals.index")}
                                                        className="inline-flex items-center gap-1 text-primary hover:underline font-medium text-xs"
                                                    >
                                                        Review di Sinyal <ExternalLinkIcon className="size-3" />
                                                    </Link>
                                                </div>
                                            </>
                                        )}

                                        {activeNode.type === "driving_force" && (
                                            <>
                                                {activeNode.description && (
                                                    <p className="m-0 text-muted-foreground">
                                                        {activeNode.description}
                                                    </p>
                                                )}
                                                <div className="flex items-center gap-2 pt-1">
                                                    <DimensionChip name={activeNode.dimension} />
                                                    <span className={cn(statusBadge(), "bg-accent text-primary")}>
                                                        {activeNode.degree} Koneksi
                                                    </span>
                                                </div>
                                            </>
                                        )}

                                        {activeNode.type === "source" && (
                                            <>
                                                <p className="m-0 text-muted-foreground">
                                                    Penerbit / Publisher: <b className="text-foreground">{activeNode.publisher || "-"}</b>
                                                </p>
                                                <span className={cn(statusBadge(), "w-fit bg-accent text-primary")}>
                                                    {activeNode.signals_count} Sinyal Diekstrak
                                                </span>
                                            </>
                                        )}
                                    </div>
                                </div>
                            )}

                            {/* Legend Bar at Bottom */}
                            <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border bg-card/90 px-4 py-2.5 text-xs backdrop-blur-md">
                                <div className="flex flex-wrap items-center gap-3">
                                    <span className="font-semibold text-foreground">Legenda Node:</span>
                                    <span className="flex items-center gap-1.5">
                                        <span className="size-3 rounded-full border-2 border-primary bg-card" />
                                        <span className="text-foreground">Driving Force (Hub Inti)</span>
                                    </span>
                                    <span className="flex items-center gap-1.5">
                                        <span
                                            className="size-2.5 rounded-full"
                                            style={{ backgroundColor: isDark ? "#2fd1c2" : "#0b8f8a" }}
                                        />
                                        <span className="text-foreground">Sinyal (Satelit Bukti)</span>
                                    </span>
                                    <span className="flex items-center gap-1.5">
                                        <span
                                            className="size-2.5 rounded-full"
                                            style={{ backgroundColor: isDark ? "#8797b0" : "#5f6d84" }}
                                        />
                                        <span className="text-foreground">Dokumen Sumber</span>
                                    </span>
                                </div>
                                <div className="text-[11px] text-muted-foreground">
                                    Gunakan roda mouse untuk Zoom, geser untuk Pan, dan klik node untuk melihat detail.
                                </div>
                            </div>
                        </div>
                    </>
                )}
            </main>
        </AuthenticatedLayout>
    );
}
