import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head } from "@inertiajs/react";
import {
    Col,
    DatePicker,
    message,
    Popconfirm,
    Row,
    Select,
    Space,
    Table,
    Tag,
    theme,
    Typography,
} from "antd";
import { Content } from "antd/es/layout/layout";
import dayjs from "dayjs";
import qs from "qs";
import { useEffect, useRef, useState } from "react";
import "../../../css/additional.css";

export default function TableRatingUrgency({ auth, title, dimensions }) {
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
    const [rating, setRating] = useState([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);

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
                `${route("rating-urgency.fetch-data")}?${qs.stringify(
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

    const columns = [
        {
            title: "No",
            dataIndex: "no",
            key: "no",
        },
        {
            title: "ITEM",
            dataIndex: "keyword",
            key: "keyword",
        },
        {
            title: "RATING",
            dataIndex: "rating",
            key: "rating",
            children: [
                {
                    title: "Uncertainty",
                    dataIndex: "uncertainty",
                    key: "uncertainty",
                    align: "center",
                    render: (text, record) => (
                        <div>
                            {rating.map((value, index) => (
                                <Popconfirm
                                    title={`Are you sure to set rating uncertainty ${value} for ${record.keyword}?`}
                                    onConfirm={() =>
                                        handleTagClick(
                                            record,
                                            "uncertainty",
                                            value
                                        )
                                    }
                                    okText="Yes"
                                    cancelText="No"
                                    key={index}
                                >
                                    <Tag
                                        className="custom-tag"
                                        color={
                                            value == record.uncertainty
                                                ? "orange"
                                                : ""
                                        }
                                        key={index}
                                    >
                                        {value}
                                    </Tag>
                                </Popconfirm>
                            ))}
                        </div>
                    ),
                },
                {
                    title: "Impact",
                    dataIndex: "impact",
                    key: "impact",
                    align: "center",
                    render: (text, record) => (
                        <div>
                            {rating.map((value, index) => (
                                <Popconfirm
                                    title={`Are you sure to set rating impact ${value} for ${record.keyword}?`}
                                    onConfirm={() =>
                                        handleTagClick(record, "impact", value)
                                    }
                                    okText="Yes"
                                    cancelText="No"
                                    key={index}
                                >
                                    <Tag
                                        className="custom-tag"
                                        color={
                                            value == record.impact
                                                ? "orange"
                                                : ""
                                        }
                                        key={index}
                                    >
                                        {value}
                                    </Tag>
                                </Popconfirm>
                            ))}
                        </div>
                    ),
                },
            ],
        },
    ];

    const showActiveDimension = (dimension) => {
        const { label } = dimensions.find((d) => d.value === dimension);

        return label.toUpperCase();
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


    const handleTagClick = async (record, type, value) => {
        const payload = {
            uuid: record.id,
            type,
            value,
        };

        setLoading(true);

        try {
            const response = await axios.post(
                route("rating-urgency.create", record.id),
                payload
            );

            if (response.status === 200 || response.status === 201) {
                message.success(response.data.message);
                setOpen(false);
                fetchData(); // Refresh the table data
            } else {
                message.error(`Failed to set rating`);
            }
        } catch (error) {
            message.error(`Error: ${error.message}`);
        } finally {
            setLoading(false);
        }
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
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
                    onChange={handleTableChange}
                    rowKey={(record) => record.id}
                    pagination={tableParams.pagination}
                    title={() => (
                        <div
                            style={{ textAlign: "center", fontWeight: "bold" }}
                        >
                            RATING OF URGENCY
                            {tableParams?.dimension
                                ? ` ▶ ${showActiveDimension(
                                      tableParams.dimension
                                  )}`
                                : " ▶ OVERALL"}
                        </div>
                    )}
                />
            </Content>
        </AuthenticatedLayout>
    );
}
