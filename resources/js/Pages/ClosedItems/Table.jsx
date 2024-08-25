import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import {
    MoreOutlined,
    DeleteOutlined,
    EditOutlined,
    BarChartOutlined,
    PlusOutlined,
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
} from "antd";
import axios from "axios";
import qs from "qs";
import dayjs from "dayjs";
import { useEffect, useRef, useState } from "react";
import Dialog from "@/Components/Dialog";

export default function TableClosedItems({ auth, title, dimensions }) {
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
                `${route("closed-items.fetch-data")}?${qs.stringify(
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
        return <Tag color="error">{status}</Tag>;
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
            render: columnStatus
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

    return (
        <AuthenticatedLayout
            auth={auth}
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    {title}
                </h2>
            }
        >
            <Head title={title} />

            <Content
                style={{
                    margin: "24px 16px 0",
                    padding: 24,
                    minHeight: 100,
                    background: colorBgContainer,
                    borderRadius: borderRadiusLG,
                }}
            >
                <Row gutter={16} style={{ marginBottom: "10px" }}>
                    <Col xs={24} sm={12} md={8} lg={6}>
                        <Title level={5}>Filter</Title>
                    </Col>
                </Row>
                <Row gutter={16}>
                    <Col xs={24} sm={12} md={8} lg={6}>
                        <Space direction="vertical" style={{ width: "100%" }}>
                            Date Period :
                            <RangePicker
                                value={defaultDate}
                                disabled={loading}
                                onChange={handleRangePickerChange}
                                format="YYYY-MM-DD"
                                style={{ width: "100%" }}
                            />
                        </Space>
                    </Col>
                    <Col xs={24} sm={12} md={8} lg={6}>
                        <Space direction="vertical" style={{ width: "100%" }}>
                            Dimension :
                            <Select
                                style={{ width: "100%" }}
                                disabled={loading}
                                placeholder="Select a dimension"
                                filterOption={(input, option) =>
                                    (option?.label ?? "")
                                        .toLowerCase()
                                        .includes(input.toLowerCase())
                                }
                                allowClear
                                onChange={(value) =>
                                    handleSelectChange("dimension", value)
                                }
                                options={dimensions}
                            />
                        </Space>
                    </Col>
                </Row>
            </Content>

            <Content
                style={{
                    margin: "24px 16px 0",
                    padding: 24,
                    background: colorBgContainer,
                    borderRadius: borderRadiusLG,
                }}
            >
                <Row gutter={16} align="top" style={{ padding: "5px" }}>
                    <Col xs={24} sm={12} md={16}>
                        <Title level={4}>{title} List</Title>
                    </Col>
                    <Col
                        xs={24}
                        sm={12}
                        md={8}
                        offset={0}
                        style={{ textAlign: "right" }}
                    >
                        <Space direction="vertical">
                            <Search
                                placeholder="input search text"
                                allowClear
                                disabled={loading}
                                onSearch={handleSearchChange}
                                style={{ width: "100%" }}
                            />
                        </Space>
                    </Col>
                </Row>
                <Table
                    dataSource={data}
                    rowKey={(record) => record.id}
                    columns={columns}
                    pagination={tableParams.pagination}
                    scroll={{ x: "max-content", y: 420 }}
                    loading={loading}
                    size="small"
                    onChange={handleTableChange}
                />
            </Content>
        </AuthenticatedLayout>
    );
}
