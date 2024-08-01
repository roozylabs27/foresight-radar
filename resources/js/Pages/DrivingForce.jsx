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
    Modal,
} from "antd";
import axios from "axios";
import qs from "qs";
import dayjs from "dayjs";
import { useEffect, useState } from "react";

export default function DrivingForce({ auth, dimensions }) {
    // Import
    const {
        token: { colorBgContainer, borderRadiusLG },
    } = theme.useToken();
    const { Content } = Layout;
    const { Title } = Typography;
    const { Search } = Input;
    const { RangePicker } = DatePicker;

    // State
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(false);
    const [open, setOpen] = useState(false);
    const [defaultDimension, setDefaultDimension] = useState(
        dimensions[0]["value"]
    );
    const [defaultDate, setDefaultDate] = useState([
        dayjs().startOf("month"),
        dayjs().endOf("month"),
    ]);
    const [tableParams, setTableParams] = useState({
        dimension: defaultDimension,
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
                console.log(error);
                setLoading(false);
            }
        } catch (error) {
            console.log(error);
            setLoading(false);
        }
    };

    const columnApproved = (text) => {
        const status = text.toLowerCase();
        switch (status) {
            case "pending":
                return <Tag color="processing">{status}</Tag>;
            case "approved":
                return <Tag color="success">{status}</Tag>;
            case "rejected":
                return <Tag color="success">{status}</Tag>;
            default:
                return <Tag color="default">{status}</Tag>;
        }
    };

    const columnAction = (text, record) => {
        const actions = [
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
                    <Flex gap="middle" vertical={false}>
                        <DeleteOutlined />
                        Delete
                    </Flex>
                ),
            },
        ];

        if (record.status === "APPROVED") {
            actions.unshift({
                key: "calculate",
                label: (
                    <Flex gap="middle" vertical={false}>
                        <BarChartOutlined />
                        Calculate
                    </Flex>
                ),
            });
        }

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
            width: 150,
        },
        {
            title: "Description",
            dataIndex: "description",
            width: 100,
        },
        {
            title: "Status",
            dataIndex: "status",
            align: "center",
            width: 60,
            render: columnApproved,
        },
        {
            title: "Admin PIC",
            dataIndex: "created_by",
            align: "center",
            width: 60,
        },
        {
            title: "Remark",
            dataIndex: "remark",
            width: 100,
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
            case "calculate":
                alert(key);
                break;
            case "edit":
                alert(key);
                break;
            case "delete":
                alert(key);
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

    const handleSelectChange = (value) => {
        setTableParams({
            ...tableParams,
            page: 1,
            pagination: {
                pageSize: 10,
                current: 1,
            },
            dimension: value,
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

    const handleModalClick = () => {
        setOpen(true);
    };

    const handleCancelModal = () => {
        setOpen(false);
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Driving Force
                </h2>
            }
        >
            <Head title="Driving Force" />

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
                                defaultValue={defaultDimension}
                                placeholder="Select a dimension"
                                filterOption={(input, option) =>
                                    (option?.label ?? "")
                                        .toLowerCase()
                                        .includes(input.toLowerCase())
                                }
                                allowClear
                                onChange={handleSelectChange}
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
                        <Title level={4}>Driving Force List</Title>
                    </Col>
                    <Col
                        xs={24}
                        sm={12}
                        md={8}
                        offset={0}
                        style={{ textAlign: "right" }}
                    >
                        <Space direction="vertical">
                            <Button type="primary" onClick={handleModalClick}>
                                <PlusOutlined />
                                Create Signal Changes
                            </Button>
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

            <Modal
                title="Create Signal Changes"
                open={open}
                // onOk={handleOk}
                // confirmLoading={confirmLoading}
                onCancel={handleCancelModal}
            >
                <p>text</p>
            </Modal>
        </AuthenticatedLayout>
    );
}
