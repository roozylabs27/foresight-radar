import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head } from "@inertiajs/react";
import { Col, DatePicker, Row, Select, Space, theme, Typography } from "antd";
import { Content } from "antd/es/layout/layout";
import dayjs from "dayjs";
import { useState } from "react";

export default function TableTimeHorizon({ auth, dimensions }) {
    // Import
    const {
        token: { colorBgContainer, borderRadiusLG },
    } = theme.useToken();
    const title = "";
    const { RangePicker } = DatePicker;
    const { Title } = Typography;
    // Table
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(false);
    const [defaultDate, setDefaultDate] = useState([
        dayjs().startOf("month"),
        dayjs().endOf("month"),
    ]);

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
        </AuthenticatedLayout>
    );
}
