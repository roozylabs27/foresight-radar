import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import {
    FileTextOutlined,
    CheckCircleOutlined,
    CloseCircleOutlined,
    LinkOutlined,
    PlusCircleOutlined,
    SearchOutlined,
    ThunderboltOutlined,
    AuditOutlined,
    EyeOutlined,
    InfoCircleOutlined,
    CloudUploadOutlined,
} from "@ant-design/icons";
import { Head } from "@inertiajs/react";
import {
    Layout,
    theme,
    Breadcrumb,
    Table,
    Tag,
    Space,
    Row,
    Col,
    Typography,
    Input,
    Select,
    Button,
    DatePicker,
    message,
    Modal,
    Card,
    Tooltip,
    Tabs,
    Form,
    Badge,
    Descriptions,
    Divider,
    Alert,
} from "antd";
import axios from "axios";
import dayjs from "dayjs";
import { useEffect, useState } from "react";

const { Content } = Layout;
const { Title, Text, Paragraph } = Typography;
const { TextArea } = Input;

export default function SignalIndex({ auth, title, dimensions, timeHorizons, existingDrivingForces }) {
    const {
        token: { colorBgContainer, borderRadiusLG },
    } = theme.useToken();

    const [activeTab, setActiveTab] = useState("review");
    const [ingestForm] = Form.useForm();
    const [ingesting, setIngesting] = useState(false);

    // Review Table State
    const [signals, setSignals] = useState([]);
    const [loadingSignals, setLoadingSignals] = useState(false);
    const [statusFilter, setStatusFilter] = useState("PENDING");
    const [dimensionFilter, setDimensionFilter] = useState(null);
    const [searchTerm, setSearchTerm] = useState("");
    const [pagination, setPagination] = useState({ current: 1, pageSize: 10, total: 0 });

    // Modals
    const [inspectSignal, setInspectSignal] = useState(null);
    const [actionModal, setActionModal] = useState({ open: false, type: null, signal: null });
    const [actionForm] = Form.useForm();
    const [actionSubmitting, setActionSubmitting] = useState(false);

    const fetchSignals = async () => {
        setLoadingSignals(true);
        try {
            const params = {
                page: pagination.current,
                pageSize: pagination.pageSize,
                status: statusFilter,
                dimension: dimensionFilter,
                search: searchTerm || undefined,
            };
            const response = await axios.get("/signals/fetch", { params });
            setSignals(response.data.data || []);
            setPagination((prev) => ({
                ...prev,
                total: response.data.total || 0,
            }));
        } catch (error) {
            message.error("Failed to load candidate signals.");
        } finally {
            setLoadingSignals(false);
        }
    };

    useEffect(() => {
        fetchSignals();
    }, [statusFilter, dimensionFilter, pagination.current, pagination.pageSize]);

    const handleSearch = () => {
        setPagination((prev) => ({ ...prev, current: 1 }));
        fetchSignals();
    };

    // Ingest Handler
    const handleIngest = async (values) => {
        setIngesting(true);
        try {
            const payload = {
                title: values.title,
                content: values.content,
                url: values.url || null,
                publisher: values.publisher || null,
                published_at: values.published_at ? values.published_at.format("YYYY-MM-DD") : null,
            };
            const res = await axios.post("/signals/ingest", payload);
            message.success(res.data.message || "Content ingested successfully.");
            ingestForm.resetFields();
            setActiveTab("review");
            setStatusFilter("PENDING");
            fetchSignals();
        } catch (err) {
            const errText = err.response?.data?.errors?.content?.[0] || err.response?.data?.errors || "Ingestion failed.";
            message.error(typeof errText === "string" ? errText : "Validation error on submission.");
        } finally {
            setIngesting(false);
        }
    };

    // Review Action Handlers
    const openActionModal = (type, signal) => {
        setActionModal({ open: true, type, signal });
        actionForm.resetFields();
        if (type === "accept_create") {
            actionForm.setFieldsValue({
                title: signal.title,
                summary: signal.summary,
                dimension_id: signal.dimension_id,
            });
        }
    };

    const handleActionSubmit = async (values) => {
        if (!actionModal.signal) return;
        setActionSubmitting(true);
        try {
            const payload = {
                action: actionModal.type,
                ...values,
            };
            const res = await axios.post(`/signals/${actionModal.signal.uuid}/review`, payload);
            message.success(res.data.message || "Review action processed.");
            setActionModal({ open: false, type: null, signal: null });
            fetchSignals();
        } catch (err) {
            message.error(err.response?.data?.errors || "Action failed to execute.");
        } finally {
            setActionSubmitting(false);
        }
    };

    const columns = [
        {
            title: "Signal Title & Evidence",
            dataIndex: "title",
            key: "title",
            width: "35%",
            render: (text, record) => (
                <Space direction="vertical" size={4} style={{ width: "100%" }}>
                    <Text strong style={{ fontSize: "14px" }}>{text}</Text>
                    <Paragraph ellipsis={{ rows: 2 }} type="secondary" style={{ marginBottom: 4, fontSize: "12px" }}>
                        {record.summary}
                    </Paragraph>
                    <Card
                        size="small"
                        style={{
                            background: "#f9fafb",
                            borderLeft: "3px solid #1677ff",
                            borderRadius: "4px",
                        }}
                        bodyStyle={{ padding: "6px 10px" }}
                    >
                        <Text style={{ fontSize: "12px", fontStyle: "italic" }}>
                            "{record.evidence_quote}"
                        </Text>
                    </Card>
                </Space>
            ),
        },
        {
            title: "Taxonomy & Origin",
            key: "dimension",
            width: "18%",
            render: (_, record) => (
                <Space direction="vertical" size={2}>
                    <Tag color="blue">{record.dimension?.name || "Unassigned"}</Tag>
                    {record.suggested_time_horizon && (
                        <Tag color="purple">{record.suggested_time_horizon.name}</Tag>
                    )}
                    <Text type="secondary" style={{ fontSize: "11px" }}>
                        Source: {record.source?.publisher || record.source?.title || "Manual entry"}
                    </Text>
                </Space>
            ),
        },
        {
            title: "Assessment & Confidence",
            key: "assessment",
            width: "18%",
            render: (_, record) => {
                const confPercent = Math.round((record.confidence_score || 0.8) * 100);
                return (
                    <Space direction="vertical" size={2}>
                        <Space>
                            <Badge
                                status={confPercent >= 80 ? "success" : "warning"}
                                text={<Text style={{ fontSize: "12px" }}>{confPercent}% confidence</Text>}
                            />
                        </Space>
                        <Text type="secondary" style={{ fontSize: "11px" }}>
                            Est. Impact: <Text strong>{record.preliminary_impact ?? "N/A"}</Text> | Uncertainty: <Text strong>{record.preliminary_uncertainty ?? "N/A"}</Text>
                        </Text>
                        <Tag color={record.is_ai_generated ? "cyan" : "default"} style={{ fontSize: "10px" }}>
                            {record.is_ai_generated ? "AI Candidate" : "Human Extracted"}
                        </Tag>
                    </Space>
                );
            },
        },
        {
            title: "Status",
            dataIndex: "review_status",
            key: "review_status",
            width: "12%",
            render: (status) => {
                const colors = { PENDING: "gold", ACCEPTED: "green", REJECTED: "red" };
                return <Tag color={colors[status] || "default"}>{status}</Tag>;
            },
        },
        {
            title: "Action",
            key: "action",
            width: "17%",
            render: (_, record) => (
                <Space direction="vertical" size={4}>
                    <Button
                        size="small"
                        icon={<EyeOutlined />}
                        onClick={() => setInspectSignal(record)}
                        style={{ width: "100%", textAlign: "left" }}
                    >
                        Inspect
                    </Button>
                    {record.review_status === "PENDING" && (
                        <>
                            <Button
                                size="small"
                                type="primary"
                                icon={<PlusCircleOutlined />}
                                onClick={() => openActionModal("accept_create", record)}
                                style={{ width: "100%", textAlign: "left" }}
                            >
                                Accept as New DF
                            </Button>
                            <Button
                                size="small"
                                icon={<LinkOutlined />}
                                onClick={() => openActionModal("accept_link", record)}
                                style={{ width: "100%", textAlign: "left" }}
                            >
                                Link to DF
                            </Button>
                            <Button
                                size="small"
                                danger
                                icon={<CloseCircleOutlined />}
                                onClick={() => openActionModal("reject", record)}
                                style={{ width: "100%", textAlign: "left" }}
                            >
                                Reject
                            </Button>
                        </>
                    )}
                </Space>
            ),
        },
    ];

    return (
        <AuthenticatedLayout auth={auth}>
            <Head title={title} />
            <Content style={{ margin: "24px 16px 0" }}>
                <Breadcrumb
                    items={[
                        { title: "Home" },
                        { title: "Strategic Foresight" },
                        { title: "Signal Ingestion & Review" },
                    ]}
                    style={{ marginBottom: "16px" }}
                />

                <Card
                    style={{
                        background: colorBgContainer,
                        borderRadius: borderRadiusLG,
                    }}
                >
                    <Row justify="space-between" align="middle" style={{ marginBottom: "16px" }}>
                        <Col>
                            <Title level={4} style={{ margin: 0 }}>
                                <ThunderboltOutlined style={{ marginRight: 8, color: "#1677ff" }} />
                                Signal-to-Insight Workspace
                            </Title>
                            <Text type="secondary">
                                Ingest external publications and review evidence-backed candidate signals before formal repository graduation.
                            </Text>
                        </Col>
                    </Row>

                    <Tabs
                        activeKey={activeTab}
                        onChange={setActiveTab}
                        items={[
                            {
                                key: "review",
                                label: (
                                    <span>
                                        <AuditOutlined style={{ marginRight: 6 }} />
                                        Candidate Review Queue
                                    </span>
                                ),
                                children: (
                                    <>
                                        {/* Filters Bar */}
                                        <Row gutter={[16, 16]} style={{ marginBottom: "16px" }} align="middle">
                                            <Col xs={24} sm={8} md={6}>
                                                <Select
                                                    value={statusFilter}
                                                    onChange={setStatusFilter}
                                                    style={{ width: "100%" }}
                                                    options={[
                                                        { value: "PENDING", label: "Review Status: Pending" },
                                                        { value: "ACCEPTED", label: "Review Status: Accepted" },
                                                        { value: "REJECTED", label: "Review Status: Rejected" },
                                                        { value: "ALL", label: "All Statuses" },
                                                    ]}
                                                />
                                            </Col>
                                            <Col xs={24} sm={8} md={6}>
                                                <Select
                                                    placeholder="Filter Dimension"
                                                    allowClear
                                                    value={dimensionFilter}
                                                    onChange={setDimensionFilter}
                                                    style={{ width: "100%" }}
                                                    options={dimensions}
                                                />
                                            </Col>
                                            <Col xs={24} sm={8} md={8}>
                                                <Input.Search
                                                    placeholder="Search title, summary, or quotes..."
                                                    value={searchTerm}
                                                    onChange={(e) => setSearchTerm(e.target.value)}
                                                    onSearch={handleSearch}
                                                    enterButton={<SearchOutlined />}
                                                />
                                            </Col>
                                            <Col xs={24} sm={24} md={4} style={{ textAlign: "right" }}>
                                                <Button onClick={() => setActiveTab("ingest")} icon={<CloudUploadOutlined />}>
                                                    Ingest New
                                                </Button>
                                            </Col>
                                        </Row>

                                        <Table
                                            rowKey="id"
                                            columns={columns}
                                            dataSource={signals}
                                            loading={loadingSignals}
                                            pagination={{
                                                ...pagination,
                                                onChange: (page, pageSize) => {
                                                    setPagination((prev) => ({ ...prev, current: page, pageSize }));
                                                },
                                            }}
                                        />
                                    </>
                                ),
                            },
                            {
                                key: "ingest",
                                label: (
                                    <span>
                                        <CloudUploadOutlined style={{ marginRight: 6 }} />
                                        Ingest Source Document / URL
                                    </span>
                                ),
                                children: (
                                    <div style={{ maxWidth: 840, margin: "0 auto", padding: "16px 0" }}>
                                        <Alert
                                            message="Evidence Provenance Guarantee"
                                            description="Every candidate signal extracted from this document will permanently retain an exact verbatim excerpt and a SHA-256 fingerprint of the source text for audit verification."
                                            type="info"
                                            showIcon
                                            style={{ marginBottom: 24 }}
                                        />

                                        <Form form={ingestForm} layout="vertical" onFinish={handleIngest}>
                                            <Row gutter={16}>
                                                <Col xs={24} sm={16}>
                                                    <Form.Item
                                                        name="title"
                                                        label="Document / Article Title"
                                                        rules={[{ required: true, message: "Please provide a source title" }]}
                                                    >
                                                        <Input placeholder="e.g. EU Cleantech Regulatory Directive 2026/42" />
                                                    </Form.Item>
                                                </Col>
                                                <Col xs={24} sm={8}>
                                                    <Form.Item name="publisher" label="Publisher / Source Organization">
                                                        <Input placeholder="e.g. European Commission, Reuters" />
                                                    </Form.Item>
                                                </Col>
                                            </Row>

                                            <Row gutter={16}>
                                                <Col xs={24} sm={16}>
                                                    <Form.Item
                                                        name="url"
                                                        label="Source URL (Optional)"
                                                        rules={[{ type: "url", message: "Enter a valid URL" }]}
                                                    >
                                                        <Input placeholder="https://ec.europa.eu/commission/presscorner/detail/en/ip_26_..." />
                                                    </Form.Item>
                                                </Col>
                                                <Col xs={24} sm={8}>
                                                    <Form.Item name="published_at" label="Publication Date">
                                                        <DatePicker style={{ width: "100%" }} />
                                                    </Form.Item>
                                                </Col>
                                            </Row>

                                            <Form.Item
                                                name="content"
                                                label="Raw Content / Document Text"
                                                rules={[
                                                    { required: true, message: "Please paste the article or document body" },
                                                    { min: 30, message: "Must be at least 30 characters" },
                                                ]}
                                            >
                                                <TextArea
                                                    rows={8}
                                                    placeholder="Paste the excerpt, report paragraphs, or article transcript here. The extraction pipeline will parse verifiable evidence sentences, suggest taxonomies, and generate candidate signals."
                                                />
                                            </Form.Item>

                                            <Form.Item>
                                                <Space>
                                                    <Button type="primary" htmlType="submit" loading={ingesting} icon={<ThunderboltOutlined />}>
                                                        Ingest & Extract Signals
                                                    </Button>
                                                    <Button onClick={() => ingestForm.resetFields()}>Clear</Button>
                                                </Space>
                                            </Form.Item>
                                        </Form>
                                    </div>
                                ),
                            },
                        ]}
                    />
                </Card>

                {/* Inspect Modal */}
                <Modal
                    title="Signal Inspection & Provenance"
                    open={!!inspectSignal}
                    onCancel={() => setInspectSignal(null)}
                    footer={[
                        <Button key="close" onClick={() => setInspectSignal(null)}>
                            Close
                        </Button>,
                    ]}
                    width={720}
                >
                    {inspectSignal && (
                        <Descriptions bordered column={1} size="small" style={{ marginTop: 16 }}>
                            <Descriptions.Item label="Signal Title">{inspectSignal.title}</Descriptions.Item>
                            <Descriptions.Item label="Summary">{inspectSignal.summary}</Descriptions.Item>
                            <Descriptions.Item label="Significance">{inspectSignal.significance || "N/A"}</Descriptions.Item>
                            <Descriptions.Item label="Verbatim Evidence Quote">
                                <Text code style={{ display: "block", padding: "8px", background: "#f5f5f5" }}>
                                    "{inspectSignal.evidence_quote}"
                                </Text>
                            </Descriptions.Item>
                            <Descriptions.Item label="Dimension">
                                <Tag color="blue">{inspectSignal.dimension?.name || "General"}</Tag>
                            </Descriptions.Item>
                            <Descriptions.Item label="Estimated Horizon">
                                {inspectSignal.suggested_time_horizon?.name || "Unassigned"}
                            </Descriptions.Item>
                            <Descriptions.Item label="Scoring Estimates">
                                Impact: {inspectSignal.preliminary_impact ?? "N/A"} | Uncertainty: {inspectSignal.preliminary_uncertainty ?? "N/A"}
                            </Descriptions.Item>
                            <Descriptions.Item label="Confidence">
                                {Math.round((inspectSignal.confidence_score || 0.8) * 100)}% ({inspectSignal.confidence_rationale || "Algorithmic heuristic"})
                            </Descriptions.Item>
                            <Descriptions.Item label="Source Title">{inspectSignal.source?.title || "N/A"}</Descriptions.Item>
                            {inspectSignal.source?.url && (
                                <Descriptions.Item label="Source URL">
                                    <a href={inspectSignal.source.url} target="_blank" rel="noreferrer">
                                        {inspectSignal.source.url}
                                    </a>
                                </Descriptions.Item>
                            )}
                            <Descriptions.Item label="Content SHA-256 Hash">
                                <Text code style={{ fontSize: "11px" }}>{inspectSignal.source?.content_hash || "N/A"}</Text>
                            </Descriptions.Item>
                            {inspectSignal.rejection_reason && (
                                <Descriptions.Item label="Rejection Reason">
                                    <Text type="danger">{inspectSignal.rejection_reason}</Text>
                                </Descriptions.Item>
                            )}
                        </Descriptions>
                    )}
                </Modal>

                {/* Review Action Modal */}
                <Modal
                    title={
                        actionModal.type === "accept_create"
                            ? "Accept Signal & Create Driving Force"
                            : actionModal.type === "accept_link"
                            ? "Accept Signal & Link to Existing Driving Force"
                            : "Reject Candidate Signal"
                    }
                    open={actionModal.open}
                    onCancel={() => setActionModal({ open: false, type: null, signal: null })}
                    onOk={() => actionForm.submit()}
                    confirmLoading={actionSubmitting}
                    okText={actionModal.type === "reject" ? "Confirm Rejection" : "Confirm Acceptance"}
                    okButtonProps={{ danger: actionModal.type === "reject" }}
                >
                    <Form form={actionForm} layout="vertical" onFinish={handleActionSubmit} style={{ marginTop: 16 }}>
                        {actionModal.type === "accept_create" && (
                            <>
                                <Paragraph type="secondary">
                                    Graduating this signal will initialize a new Driving Force in <strong>PENDING</strong> status. It will immediately be accessible in the existing 6-stage lifecycle for time horizon rating and approval.
                                </Paragraph>
                                <Form.Item
                                    name="title"
                                    label="Driving Force Title / Keyword (First 4 words will form keyword)"
                                    rules={[{ required: true, message: "Title is required" }]}
                                >
                                    <Input />
                                </Form.Item>
                                <Form.Item
                                    name="summary"
                                    label="Strategic Description"
                                    rules={[{ required: true, message: "Description is required" }]}
                                >
                                    <TextArea rows={3} />
                                </Form.Item>
                                <Form.Item
                                    name="dimension_id"
                                    label="Taxonomy Dimension"
                                    rules={[{ required: true, message: "Select dimension" }]}
                                >
                                    <Select options={dimensions} />
                                </Form.Item>
                            </>
                        )}

                        {actionModal.type === "accept_link" && (
                            <>
                                <Paragraph type="secondary">
                                    Attach this signal as verifiable supporting evidence to an existing active Driving Force.
                                </Paragraph>
                                <Form.Item
                                    name="driving_force_id"
                                    label="Target Driving Force"
                                    rules={[{ required: true, message: "Select target driving force" }]}
                                >
                                    <Select
                                        showSearch
                                        optionFilterProp="label"
                                        placeholder="Select driving force to attach evidence"
                                        options={existingDrivingForces}
                                    />
                                </Form.Item>
                            </>
                        )}

                        {actionModal.type === "reject" && (
                            <>
                                <Paragraph type="secondary">
                                    Provide feedback on why this candidate signal does not meet strategic foresight criteria.
                                </Paragraph>
                                <Form.Item
                                    name="rejection_reason"
                                    label="Rejection Reason"
                                    rules={[{ required: true, message: "Please provide a reason for rejection" }]}
                                >
                                    <TextArea rows={3} placeholder="e.g. Redundant with existing Driving Force #4; lacks verifiable market impact." />
                                </Form.Item>
                            </>
                        )}
                    </Form>
                </Modal>
            </Content>
        </AuthenticatedLayout>
    );
}
