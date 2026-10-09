import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import {
    CheckOutlined,
    CloseOutlined,
    SafetyCertificateOutlined,
    FilterOutlined,
    CalendarOutlined,
    AppstoreOutlined,
    ReloadOutlined,
} from "@ant-design/icons";
import { Head } from "@inertiajs/react";
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
} from "antd";
import axios from "axios";
import qs from "qs";
import dayjs from "dayjs";
import { useEffect, useRef, useState } from "react";
import "../../../css/additional.css";

export default function TableApproval({ auth, title, dimensions }) {
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
    const [loadingButton, setLoadingButton] = useState(false);
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

    useEffect(() => {
        fetchData();
    }, [
        tableParams.pagination?.pageSize,
        tableParams.pagination?.current,
        tableParams?.date,
        tableParams?.search,
        tableParams?.dimension,
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
                `${route("approval-items.fetch-data")}?${qs.stringify(
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

    const columnStatus = (text, record) => {
        const status = text.toLowerCase();
        return (
            <Tag color={text == "PENDING" ? "processing" : "success"}>
                {status} {status != "pending" ? `- ${record.approved_at}` : ""}
            </Tag>
        );
    };

    const columnApprovedBtn = (text, record) => {
        const textItem = record.status == "PENDING" ? "show" : "take out";
        const icon =
            record.status == "PENDING" ? <CheckOutlined /> : <CloseOutlined />;
        const value = record.status == "PENDING" ? "APPROVED" : "CLOSED";
        return (
            <Popconfirm
                title={`Are you sure to ${textItem} this ${record.keyword}?`}
                onConfirm={() =>
                    handleApproveClick(record, { value, text: textItem })
                }
                okText="Yes"
                cancelText="No"
            >
                <Button
                    type="primary"
                    loading={loadingButton}
                    shape="round"
                    icon={icon}
                    iconPosition="end"
                >
                    {textItem}
                </Button>
            </Popconfirm>
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
            align: "center",
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
            width: 100,
        },
        {
            title: "Personal In Charge",
            dataIndex: "pic",
            align: "center",
            width: 60,
        },
        {
            title: "Time Horizon",
            dataIndex: "time_horizon",
            align: "center",
            width: 60,
        },
        {
            title: "Priority",
            dataIndex: "priority",
            align: "center",
            width: 60,
        },
        {
            title: "Uncertainty",
            dataIndex: "uncertainty_analysis",
            align: "center",
            width: 60,
        },
        {
            title: "Impact",
            dataIndex: "impact_analysis",
            align: "center",
            width: 60,
        },
        {
            title: "Status",
            dataIndex: "status",
            align: "center",
            width: 60,
            render: columnStatus,
        },
        {
            title: "Status Action",
            align: "center",
            dataIndex: "status_action",
            width: 60,
        },
        {
            title: "Reason",
            dataIndex: "reason",
            width: 60,
        },
    ];
    const { user, permissions } = auth;

    if (permissions.includes("create-approval-items")) {
        columns.push({
            title: "",
            key: "operation",
            align: "center",
            render: columnApprovedBtn,
            width: 60,
        });
    }

    const handleApproveClick = async (record, { value, text }) => {
        setLoadingButton(true);
        const payload = {
            status: value,
            text,
        };

        try {
            const response = await axios.post(
                route("approval-items.create", record.id),
                payload
            );

            if (response.status === 200 || response.status === 201) {
                message.success(response.data.message);

                fetchData(); // Refresh the table data
            } else {
                message.error(`Failed to approve items`);
            }
        } catch (error) {
            message.error(`Error: ${error.message}`);
        } finally {
            setLoadingButton(false);
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

    return (
        <AuthenticatedLayout
            auth={auth}
            header={
                <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
                    <SafetyCertificateOutlined style={{ color: "#1677ff", fontSize: 18 }} />
                    <Title level={4} style={{ margin: 0, color: "#1f1f1f" }}>
                        {title}
                    </Title>
                    <Tag
                        color="blue"
                        bordered={false}
                        style={{ fontSize: 11, fontWeight: 600, borderRadius: 10 }}
                    >
                        Review & Consensus
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
                                {title} List
                            </Title>
                        </Col>
                        <Col>
                            <Search
                                placeholder="Cari data persetujuan..."
                                allowClear
                                disabled={loading}
                                value={tableParams.search}
                                onSearch={handleSearchChange}
                                onChange={(e) => {
                                    if (e.target.value === "") {
                                        handleSearchChange("");
                                    }
                                }}
                                style={{ width: 240 }}
                                size="middle"
                            />
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
        </AuthenticatedLayout>
    );
}
