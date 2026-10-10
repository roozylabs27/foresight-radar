import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import {
    MoreOutlined,
    DeleteOutlined,
    EditOutlined,
    BarChartOutlined,
    PlusOutlined,
    FilterOutlined,
    CalendarOutlined,
    AppstoreOutlined,
    ReloadOutlined,
    RadarChartOutlined,
    ThunderboltOutlined,
    GlobalOutlined,
    ArrowRightOutlined,
} from "@ant-design/icons";
import { Head, Link } from "@inertiajs/react";
import {
    Layout,
    theme,
    Breadcrumb,
    Table,
    Tag,
    Dropdown,
    Space,
    Flex,
    Row,
    Col,
    Typography,
    Input,
    Select,
    Button,
    DatePicker,
    message,
    Popconfirm,
    Card,
    Tooltip,
    Drawer,
    List,
    Badge,
} from "antd";
import axios from "axios";
import qs from "qs";
import dayjs from "dayjs";
import { useEffect, useRef, useState } from "react";
import Dialog from "@/Components/Dialog";
import FormDrivingForce from "./Form";

export default function TableDrivingForce({ auth, title , dimensions, users }) {
    // Import
    const {
        token: { colorBgContainer, borderRadiusLG },
    } = theme.useToken();
    const { Content } = Layout;
    const { Title } = Typography;
    const { Search } = Input;
    const { RangePicker } = DatePicker;

    // Table
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(false);
    const [defaultDate, setDefaultDate] = useState([
        dayjs().startOf("month"),
        dayjs().endOf("month"),
    ]);
    const [tableParams, setTableParams] = useState({
        pagination: {
            current: 1,
            pageSize: 10,
        },
        date: [
            dayjs().startOf("month").format("YYYY-MM-DD"),
            dayjs().endOf("month").format("YYYY-MM-DD"),
        ],
    });

    // Modal
    const formRef = useRef(null);
    const [errors, setErrors] = useState({});
    const [open, setOpen] = useState(false);
    const [titleModal, setTitleModal] = useState("");
    const [isEditMode, setIsEditMode] = useState(false);
    const [initialValues, setInitialValues] = useState({});

    useEffect(() => {
        fetchData();
    }, [
        tableParams.pagination?.pageSize,
        tableParams.pagination?.current,
        tableParams?.date,
        tableParams?.search,
        tableParams?.dimension,
        // tableParams?.status,
    ]);

    const getParams = (params) => {
        return {
            results: params.pagination?.pageSize,
            page: params.pagination?.current,
            ...params,
        };
    };

    const fetchData = async () => {
        setLoading(true);
        try {
            const response = await axios.get(
                `${route("driving-force.fetch-data")}?${qs.stringify(
                    getParams(tableParams)
                )}`
            );

            if (response.status == 200) {
                setTimeout(() => {
                    setData(response.data.data);
                    setTableParams({
                        ...tableParams,
                        pagination: {
                            pageSize: response.data.meta.per_page,
                            current: response.data.meta.current_page,
                            total: response.data.meta.total,
                        },
                    });
                    setLoading(false);
                }, 500);
            } else {
                message.error(`Error: ${error.message}`);
                setLoading(false);
            }
        } catch (error) {
            // TODO: Handling error
            message.error(`Error: ${error.message}`);
            setLoading(false);
        }
    };

    // Drawer for Signals Provenance
    const [signalsDrawerOpen, setSignalsDrawerOpen] = useState(false);
    const [selectedDrivingForceSignals, setSelectedDrivingForceSignals] = useState(null);

    const handleViewSignals = (record) => {
        setSelectedDrivingForceSignals(record);
        setSignalsDrawerOpen(true);
    };

    const columnAction = (text, record) => {
        const actions = [
            {
                key: "signals",
                label: (
                    <Flex gap="middle" vertical={false}>
                        <ThunderboltOutlined style={{ color: "#1677ff" }} />
                        Sinyal Asal ({record.signals_count || 0})
                    </Flex>
                ),
            },
            {
                key: "edit",
                label: (
                    <Flex gap="middle" vertical={false}>
                        <EditOutlined />
                        Edit
                    </Flex>
                ),
            },
            {
                key: "delete",
                label: (
                    <Popconfirm
                        title="Delete signal changes"
                        description="Are you sure to delete this signal changes ?"
                        onConfirm={() => handleDeleteSignal(record)}
                        okText="Yes"
                        cancelText="No"
                    >
                        <Flex gap="middle" vertical={false}>
                            <DeleteOutlined />
                            Delete
                        </Flex>
                    </Popconfirm>
                ),
            },
        ];

        return (
            <Dropdown
                placement="topLeft"
                menu={{
                    items: actions,
                    onClick: ({ key }) => handleDropdownClick(key, record),
                }}
                trigger={["hover"]}
            >
                <a onClick={(e) => e.preventDefault()}>
                    <Space>
                        <MoreOutlined />
                    </Space>
                </a>
            </Dropdown>
        );
    };

    const columns = [
        {
            title: "Date Created",
            dataIndex: "date_created",
            width: 100,
        },
        {
            title: "Dimension",
            dataIndex: "dimension",
            width: 100,
        },
        {
            title: "Keyword",
            dataIndex: "keyword",
            width: 50,
        },
        {
            title: "Description",
            dataIndex: "description",
            width: 200,
        },
        {
            title: "Personal In Charge",
            dataIndex: "pic",
            width: 60,
        },
        {
            title: "Signals",
            dataIndex: "signals_count",
            align: "center",
            width: 70,
            render: (count, record) => {
                if (!count || count === 0) {
                    return <Tag color="default" style={{ fontSize: 11, borderRadius: 10 }}>Direct</Tag>;
                }
                return (
                    <Tag
                        color="blue"
                        style={{ cursor: "pointer", fontSize: 11, borderRadius: 10 }}
                        onClick={() => handleViewSignals(record)}
                    >
                        <ThunderboltOutlined style={{ marginRight: 4 }} />
                        {count} sinyal
                    </Tag>
                );
            },
        },
        {
            title: "Pipeline Stage",
            dataIndex: "pipeline",
            align: "center",
            width: 140,
            render: (pipeline) => {
                if (!pipeline) return null;

                return (
                    <Flex vertical align="center" gap={4}>
                        <Tag
                            color={pipeline.badge_color}
                            style={{
                                fontSize: 11,
                                borderRadius: 10,
                                margin: 0,
                                fontWeight: 500,
                            }}
                        >
                            {pipeline.label}
                        </Tag>
                        {pipeline.next_url && (
                            <Link href={pipeline.next_url}>
                                <Button
                                    type="link"
                                    size="small"
                                    style={{
                                        padding: 0,
                                        height: "auto",
                                        fontSize: 11,
                                        display: "inline-flex",
                                        alignItems: "center",
                                        gap: 2,
                                    }}
                                >
                                    <span>Lanjut ke {pipeline.next_label}</span>
                                    <ArrowRightOutlined style={{ fontSize: 10 }} />
                                </Button>
                            </Link>
                        )}
                    </Flex>
                );
            },
        },
        {
            title: "",
            key: "operation",
            fixed: "right",
            align: "right",
            width: 20,
            render: columnAction,
        },
    ];

    const handleDropdownClick = (key, record) => {
        switch (key) {
            case "signals":
                handleViewSignals(record);
                break;
            case "edit":
                setOpen(true);
                setLoading(true);
                setTimeout(() => {
                    setLoading(false);
                }, 500);
                setTitleModal("Update Signal Changes");
                setIsEditMode(true);
                setInitialValues(record);
                break;
            default:
                return;
        }
    };

    const handleSearchChange = (value) => {
        setTableParams({
            ...tableParams,
            page: 1,
            pagination: {
                pageSize: 10,
                current: 1,
            },
            search: value,
        });
    };

    const handleRangePickerChange = (dates) => {
        if (dates && dates.length === 2) {
            const formattedDates = dates.map((date) =>
                date.format("YYYY-MM-DD")
            );

            setDefaultDate(dates);
            setTableParams((prevParams) => ({
                ...prevParams,
                date: formattedDates,
            }));
        } else {
            const newStartDate = dayjs().startOf("month").format("YYYY-MM-DD");
            const newEndDate = dayjs().endOf("month").format("YYYY-MM-DD");

            setDefaultDate([dayjs(newStartDate), dayjs(newEndDate)]);
            setTableParams((prevParams) => ({
                ...prevParams,
                date: [newStartDate, newEndDate],
            }));
        }
    };

    const handleSelectChange = (field, value) => {
        setTableParams({
            ...tableParams,
            page: 1,
            pagination: {
                pageSize: 10,
                current: 1,
            },
            [field]: value,
        });
    };

    const handleTableChange = (pagination, filters, sorter) => {
        setTableParams({
            pagination,
            date: tableParams.date,
            dimension: tableParams.dimension,
            search: tableParams.search,
        });

        if (pagination.pageSize !== tableParams.pagination?.pageSize) {
            setData([]);
        }
    };

    const handleCreateSignal = () => {
        setOpen(true);
        setTitleModal("Create Signal Changes");
        setInitialValues({});
    };

    const handleDeleteSignal = async (record) => {
        setLoading(true);

        try {
            const response = await axios.delete(
                route("driving-force.delete", `${record.id}`)
            );

            if (response.status === 200 || response.status === 201) {
                message.success(
                    response.data.message
                );
                fetchData(); // Refresh the table data
            } else {
                message.error(`Failed to delete signal changes`);
            }
        } catch (error) {
            if (error.response.status === 422) {
                setErrors(error.response.data.errors);
            }
            message.error(`Error: validation failed`);
        } finally {
            setLoading(false);
        }
    };

    const handleFormSubmit = async (values) => {
        setLoading(true);

        try {
            const response = isEditMode
                ? await axios.put(
                      route("driving-force.update", `${initialValues.id}`),
                      values
                  )
                : await axios.post(route("driving-force.create"), values);

            if (response.status === 200 || response.status === 201) {
                message.success(
                    response.data.message
                );
                setOpen(false);
                fetchData(); // Refresh the table data
            } else {
                message.error(
                    `Failed to ${
                        isEditMode ? "update" : "create"
                    } signal changes`
                );
            }
        } catch (error) {
            // TODO: Handling error
            if (error.response.status === 422) {
                setErrors(error.response.data.errors);
                message.error(`Error: validation failed`);
            }
        } finally {
            setLoading(false);
        }
    };

    const handleOkModal = () => {
        if (formRef.current) {
            setErrors({});
            formRef.current.submit();
        }
    };

    const handleResetFilter = () => {
        const start = dayjs().startOf("month");
        const end = dayjs().endOf("month");
        setDefaultDate([start, end]);
        setTableParams((prev) => ({
            ...prev,
            page: 1,
            pagination: { ...prev.pagination, current: 1 },
            date: [start.format("YYYY-MM-DD"), end.format("YYYY-MM-DD")],
            dimension: null,
            search: "",
        }));
    };

    const isFiltered = !!tableParams.dimension || !!tableParams.search;

    const handleCancelModal = () => {
        setErrors({});
        formRef.current.reset();
        setIsEditMode(false);
        setOpen(false);
    };

    return (
        <AuthenticatedLayout
            auth={auth}
            header={
                <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
                    <RadarChartOutlined style={{ color: "#1677ff", fontSize: 18 }} />
                    <Title level={4} style={{ margin: 0, color: "#1f1f1f" }}>
                        {title}
                    </Title>
                    <Tag
                        color="blue"
                        bordered={false}
                        style={{ fontSize: 11, fontWeight: 600, borderRadius: 10 }}
                    >
                        Signal Repository
                    </Tag>
                </div>
            }
        >
            <Head title={title} />

            <Content
                style={{
                    padding: "12px 18px 8px",
                    display: "flex",
                    flexDirection: "column",
                    minHeight: "calc(100vh - 104px)",
                    boxSizing: "border-box",
                }}
            >
                {/* Compact Single-Row Filter Toolbar */}
                <div className="radar-filter-bar">
                    <div
                        style={{
                            display: "flex",
                            alignItems: "center",
                            gap: 14,
                            flexWrap: "wrap",
                        }}
                    >
                        <div
                            style={{
                                display: "flex",
                                alignItems: "center",
                                gap: 6,
                                color: "#1677ff",
                                fontWeight: 600,
                                fontSize: 13,
                            }}
                        >
                            <FilterOutlined />
                            <span>Filter Analisis:</span>
                        </div>

                        {/* Periode Tanggal */}
                        <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                            <span
                                style={{
                                    fontSize: 12,
                                    color: "#6b7280",
                                    fontWeight: 500,
                                }}
                            >
                                <CalendarOutlined style={{ marginRight: 4 }} />
                                Periode:
                            </span>
                            <RangePicker
                                value={defaultDate}
                                disabled={loading}
                                onChange={handleRangePickerChange}
                                format="YYYY-MM-DD"
                                style={{ width: 230 }}
                                size="middle"
                            />
                        </div>

                        {/* Dimensi */}
                        <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                            <span
                                style={{
                                    fontSize: 12,
                                    color: "#6b7280",
                                    fontWeight: 500,
                                }}
                            >
                                <AppstoreOutlined style={{ marginRight: 4 }} />
                                Dimensi:
                            </span>
                            <Select
                                style={{ width: 210 }}
                                disabled={loading}
                                placeholder="Semua Dimensi"
                                filterOption={(input, option) =>
                                    (option?.label ?? "")
                                        .toLowerCase()
                                        .includes(input.toLowerCase())
                                }
                                allowClear
                                value={tableParams.dimension}
                                onChange={(value) =>
                                    handleSelectChange("dimension", value)
                                }
                                options={dimensions}
                                size="middle"
                            />
                        </div>
                    </div>

                    {isFiltered && (
                        <Tooltip title="Kembalikan filter ke kondisi awal">
                            <Button
                                type="text"
                                size="small"
                                icon={<ReloadOutlined />}
                                onClick={handleResetFilter}
                                style={{ color: "#ff4d4f", fontSize: 12 }}
                            >
                                Reset Filter
                            </Button>
                        </Tooltip>
                    )}
                </div>

                <Card
                    bordered={false}
                    style={{
                        borderRadius: borderRadiusLG,
                        boxShadow: "0 1px 3px rgba(0,0,0,0.05)",
                        flex: 1,
                        display: "flex",
                        flexDirection: "column",
                    }}
                    bodyStyle={{ padding: "16px 20px", flex: 1, display: "flex", flexDirection: "column" }}
                >
                    <Row justify="space-between" align="middle" style={{ marginBottom: 16 }}>
                        <Col>
                            <Title level={5} style={{ margin: 0 }}>
                                Daftar Sinyal Perubahan
                            </Title>
                        </Col>
                        <Col>
                            <Space size={10}>
                                <Search
                                    placeholder="Cari kata kunci..."
                                    allowClear
                                    disabled={loading}
                                    value={tableParams.search}
                                    onSearch={handleSearchChange}
                                    onChange={(e) => {
                                        if (e.target.value === "") {
                                            handleSearchChange("");
                                        }
                                    }}
                                    style={{ width: 220 }}
                                    size="middle"
                                />
                                <Button
                                    disabled={loading}
                                    type="primary"
                                    icon={<PlusOutlined />}
                                    onClick={handleCreateSignal}
                                >
                                    Tambah Sinyal
                                </Button>
                            </Space>
                        </Col>
                    </Row>

                    <Table
                        dataSource={data}
                        rowKey={(record) => record.id}
                        columns={columns}
                        pagination={tableParams.pagination}
                        scroll={{ x: "max-content", y: 440 }}
                        loading={loading}
                        size="middle"
                        onChange={handleTableChange}
                    />
                </Card>
            </Content>

            <Dialog
                title={titleModal}
                open={open}
                loading={loading}
                isEditMode={isEditMode}
                onOk={handleOkModal}
                onCancel={handleCancelModal}
            >
                <FormDrivingForce
                    ref={formRef}
                    isEditMode={isEditMode}
                    initialValues={initialValues}
                    dimensions={dimensions}
                    users={users}
                    errors={errors}
                    loading={loading}
                    onFinish={handleFormSubmit}
                />
            </Dialog>

            <Drawer
                title={
                    <Space>
                        <ThunderboltOutlined style={{ color: "#1677ff" }} />
                        <span>Originating Intelligence: {selectedDrivingForceSignals?.keyword}</span>
                    </Space>
                }
                width={560}
                open={signalsDrawerOpen}
                onClose={() => setSignalsDrawerOpen(false)}
            >
                {(!selectedDrivingForceSignals?.signals_count || selectedDrivingForceSignals.signals_count === 0) ? (
                    <div style={{ padding: "32px 16px", textAlign: "center", color: "#8c8c8c" }}>
                        <p style={{ fontSize: 15, fontWeight: 500, marginBottom: 8 }}>Tidak ada sinyal terhubung</p>
                        <p style={{ fontSize: 13 }}>Driving force ini dibuat secara manual tanpa melalui promosi sinyal lemah eksternal.</p>
                    </div>
                ) : (
                    <div>
                        {selectedDrivingForceSignals.source_signal && (
                            <Card
                                size="small"
                                title={
                                    <Space>
                                        <Tag color="purple">Primary Origin Signal</Tag>
                                        <span style={{ fontWeight: 600 }}>{selectedDrivingForceSignals.source_signal.title}</span>
                                    </Space>
                                }
                                style={{ marginBottom: 16, borderColor: "#d3adf7" }}
                            >
                                <p style={{ color: "#4b5563", fontSize: 13, marginBottom: 12 }}>
                                    {selectedDrivingForceSignals.source_signal.summary}
                                </p>
                                {selectedDrivingForceSignals.source_signal.source_url && (
                                    <div style={{ display: "flex", alignItems: "center", gap: 6, fontSize: 12 }}>
                                        <GlobalOutlined style={{ color: "#1677ff" }} />
                                        <span style={{ color: "#6b7280" }}>Sumber:</span>
                                        <a
                                            href={selectedDrivingForceSignals.source_signal.source_url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            style={{ color: "#1677ff", wordBreak: "break-all" }}
                                        >
                                            {selectedDrivingForceSignals.source_signal.source_title || selectedDrivingForceSignals.source_signal.source_url}
                                        </a>
                                    </div>
                                )}
                            </Card>
                        )}

                        {selectedDrivingForceSignals.supporting_signals?.length > 0 && (
                            <div>
                                <Typography.Title level={5} style={{ marginTop: 16, marginBottom: 12 }}>
                                    Supporting Evidence Signals ({selectedDrivingForceSignals.supporting_signals.length})
                                </Typography.Title>
                                <List
                                    dataSource={selectedDrivingForceSignals.supporting_signals}
                                    renderItem={(item) => (
                                        <List.Item style={{ padding: "10px 0" }}>
                                            <Card size="small" style={{ width: "100%" }}>
                                                <div style={{ fontWeight: 600, fontSize: 14, marginBottom: 6 }}>
                                                    {item.title}
                                                </div>
                                                {item.summary && (
                                                    <p style={{ color: "#4b5563", fontSize: 13, marginBottom: 8 }}>
                                                        {item.summary}
                                                    </p>
                                                )}
                                                {item.notes && (
                                                    <p style={{ fontStyle: "italic", color: "#6b7280", fontSize: 12, marginBottom: 8 }}>
                                                        Catatan: {item.notes}
                                                    </p>
                                                )}
                                                {item.source_url && (
                                                    <div style={{ display: "flex", alignItems: "center", gap: 6, fontSize: 12 }}>
                                                        <GlobalOutlined style={{ color: "#1677ff" }} />
                                                        <a
                                                            href={item.source_url}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            style={{ color: "#1677ff", wordBreak: "break-all" }}
                                                        >
                                                            {item.source_title || item.source_url}
                                                        </a>
                                                    </div>
                                                )}
                                            </Card>
                                        </List.Item>
                                    )}
                                />
                            </div>
                        )}
                    </div>
                )}
            </Drawer>
        </AuthenticatedLayout>
    );
}
