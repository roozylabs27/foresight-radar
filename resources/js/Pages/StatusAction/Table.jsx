import Dialog from "@/Components/Dialog";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { AimOutlined, MoreOutlined } from "@ant-design/icons";
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
                setInitialValues(record);
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
                </Row>
                <Table
                    columns={columns}
                    dataSource={data}
                    bordered
                    loading={loading}
                    rowKey={(record) => record.id}
                    onChange={handleTableChange}
                    pagination={tableParams.pagination}
                    title={() => (
                        <div
                            style={{ textAlign: "center", fontWeight: "bold" }}
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
            </Content>

            <Dialog
                title={titleModal}
                open={open}
                loading={loading}
                btnText="Set"
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
