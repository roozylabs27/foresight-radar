import Dialog from "@/Components/Dialog";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import {
    AimOutlined,
    MoreOutlined,
    CheckCircleOutlined,
    FilterOutlined,
    CalendarOutlined,
    AppstoreOutlined,
    ReloadOutlined,
} from "@ant-design/icons";
import { Head } from "@inertiajs/react";
import {
    Col,
    DatePicker,
    Dropdown,
    Flex,
    message,
    Row,
    Select,
    Space,
    Table,
    theme,
    Typography,
    Card,
    Tag,
    Tooltip,
    Button,
} from "antd";
import { Content } from "antd/es/layout/layout";
import dayjs from "dayjs";
import qs from "qs";
import { useEffect, useRef, useState } from "react";
import FormStatusAction from "./Form";

export default function TableStatusAction({
    auth,
    title,
    dimensions,
    status_actions,
}) {
    // Import
    const {
        token: { colorBgContainer, borderRadiusLG },
    } = theme.useToken();
    const { RangePicker } = DatePicker;
    const { Title } = Typography;

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
    const [initialValues, setInitialValues] = useState({});

    useEffect(() => {
        fetchData();
    }, [
        tableParams.pagination?.pageSize,
        tableParams.pagination?.current,
        tableParams?.date,
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
                `${route("status-action.fetch-data")}?${qs.stringify(
                    getParams(tableParams)
                )}`
            );

            if (response.status == 200) {
                setTimeout(() => {
                    const newData = response.data.data.map((d, i) => ({
                        no: i + 1,
                        ...d,
                    }));
                    setData(newData);
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

    const handleDropdownClick = (key, record) => {
        switch (key) {
            case "status action":
                setOpen(true);
                setLoading(true);
                setTimeout(() => {
                    setLoading(false);
                }, 500);
                setTitleModal(`Set Status Action - ${record.keyword}`);
                const newInitialValues = {
                    ...record,
                    status_action_id : null,
                    reason : null
                }
                setInitialValues(newInitialValues);
                break;
            default:
                return;
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

    const columnAction = (text, record) => {
        const actions = [
            {
                key: "status action",
                label: (
                    <Flex gap="middle" vertical={false}>
                        <AimOutlined />
                        Set Status Action
                    </Flex>
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
            title: "No",
            dataIndex: "no",
            key: "no",
            width: 10,
            align: "center",
        },
        {
            title: "Keyword",
            dataIndex: "keyword",
            key: "keyword",
            width: 250,
        },
        {
            title: "MONITORING",
            dataIndex: "monitoring",
            key: "monitoring",
            align: "center",
        },
        {
            title: "DECIDED PLAN",
            dataIndex: "decided_plan",
            key: "decided_plan",
            align: "center",
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

    const showActiveDimension = (dimension) => {
        const { label } = dimensions.find((d) => d.value === dimension);

        return label.toUpperCase();
    };

    const handleOkModal = () => {
        if (formRef?.current) {
            setErrors({});
            formRef.current.submit();
        }
    };

    const handleCancelModal = () => {
        if (formRef?.current) {
            setErrors({});
            formRef.current.reset();
        }
        setOpen(false);
    };

    const handleTableChange = (pagination, filters, sorter) => {
        setTableParams({
            pagination,
            date: tableParams.date,
            dimension: tableParams.dimension,
        });

        if (pagination.pageSize !== tableParams.pagination?.pageSize) {
            setData([]);
        }
    };

    const handleFormSubmit = async (values) => {
        setLoading(true);

        try {
            const response = await axios.post(
                route("status-action.create", initialValues.id),
                values
            );

            if (response.status === 200 || response.status === 201) {
                message.success(response.data.message);
                setOpen(false);
                fetchData(); // Refresh the table data
            } else {
                message.error(`Failed to create status action`);
            }
        } catch (error) {
            // TODO: Handling error
            console.log(error)
            // if (error.response.status === 422) {
            //     setErrors(error.response.data.errors);
            //     message.error(`Error: validation failed`);
            // } else {
            //     message.error(error);
            // }
        } finally {
            setLoading(false);
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
        }));
    };

    const isFiltered = !!tableParams.dimension;

    return (
        <AuthenticatedLayout
            auth={auth}
            header={
                <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
                    <AimOutlined style={{ color: "#1677ff", fontSize: 18 }} />
                    <Title level={4} style={{ margin: 0, color: "#1f1f1f" }}>
                        {title}
                    </Title>
                    <Tag
                        color="blue"
                        bordered={false}
                        style={{ fontSize: 11, fontWeight: 600, borderRadius: 10 }}
                    >
                        Strategic Actions
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
                    <Table
                        columns={columns}
                        dataSource={data}
                        bordered
                        size="middle"
                        loading={loading}
                        rowKey={(record) => record.id}
                        onChange={handleTableChange}
                        pagination={tableParams.pagination}
                        scroll={{ x: "max-content", y: 440 }}
                        title={() => (
                            <div
                                style={{ textAlign: "center", fontWeight: 600, color: "#1f1f1f" }}
                            >
                                STATUS OF ACTION
                                {tableParams?.dimension
                                    ? ` ▶ ${showActiveDimension(
                                          tableParams.dimension
                                      )}`
                                    : " ▶ OVERALL"}
                            </div>
                        )}
                    />
                </Card>
            </Content>

            <Dialog
                title={titleModal}
                open={open}
                loading={loading}
                btnText="Save Changes"
                width={1500}
                onOk={handleOkModal}
                onCancel={handleCancelModal}
            >
                <FormStatusAction
                    ref={formRef}
                    isEditMode={false}
                    initialValues={initialValues}
                    errors={errors}
                    status_actions={status_actions}
                    loading={loading}
                    onFinish={handleFormSubmit}
                />
            </Dialog>
        </AuthenticatedLayout>
    );
}
