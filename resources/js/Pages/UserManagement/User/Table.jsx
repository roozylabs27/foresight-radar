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
import FormUser from "./Form";

export default function TableUser({ auth, title, roles }) {
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
        tableParams?.status,
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
                `${route("user-management.user.fetch-data")}?${qs.stringify(
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
                    <Popconfirm
                        title="Delete user"
                        description="Are you sure to delete this user ?"
                        onConfirm={() => handleDeleteButton(record)}
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
            title: "Name",
            dataIndex: "name",
            width: 100,
        },
        {
            title: "Email",
            dataIndex: "email",
            width: 150,
        },
        {
            title: "Role",
            dataIndex: "role",
            width: 150,
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
            case "edit":
                setOpen(true);
                setLoading(true);
                setTimeout(() => {
                    setLoading(false);
                }, 500);
                setTitleModal(`Update User - ${record.name}`);
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

    const handleCreateButton = () => {
        setOpen(true);
        setTitleModal("Create User");
        setInitialValues({});
    };

    const handleDeleteButton = async (record) => {
        setLoading(true);

        try {
            const response = await axios.delete(
                route("user-management.user.delete", `${record.id}`)
            );

            if (response.status === 200 || response.status === 201) {
                message.success(response.data.message);
                fetchData(); // Refresh the table data
            } else {
                message.error(`Failed to delete user ${record.name}`);
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
                      route(
                          "user-management.user.update",
                          `${initialValues.id}`
                      ),
                      values
                  )
                : await axios.post(
                      route("user-management.user.create"),
                      values
                  );

            if (response.status === 200 || response.status === 201) {
                message.success(response.data.message);
                setOpen(false);
                fetchData(); // Refresh the table data
            } else {
                message.error(
                    `Failed to ${isEditMode ? "update" : "create"} user`
                );
            }
        } catch (error) {
            console.log(error)
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
                    minHeight: "auto",
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
                            <Button
                                disabled={loading}
                                type="primary"
                                onClick={handleCreateButton}
                            >
                                <PlusOutlined />
                                Create User
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

            <Dialog
                title={titleModal}
                open={open}
                loading={loading}
                isEditMode={isEditMode}
                onOk={handleOkModal}
                onCancel={handleCancelModal}
            >
                <FormUser
                    ref={formRef}
                    isEditMode={isEditMode}
                    initialValues={initialValues}
                    roles={roles}
                    errors={errors}
                    loading={loading}
                    onFinish={handleFormSubmit}
                />
            </Dialog>
        </AuthenticatedLayout>
    );
}
