import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head } from "@inertiajs/react";
import {
    Layout,
    theme,
    Breadcrumb,
    Tabs,
    Row,
    Col,
    Space,
    DatePicker,
    Select,
} from "antd";
import Title from "antd/es/typography/Title";
import { useState } from "react";
import dayjs from "dayjs";
import Radar from "@/Components/Radar";

export default function ForesightRadar({ auth, dimensions, title }) {
    const {
        token: { colorBgContainer, borderRadiusLG },
    } = theme.useToken();
    const { Content } = Layout;
    const { RangePicker } = DatePicker;

    const [defaultDate, setDefaultDate] = useState([
        dayjs().startOf("month"),
        dayjs().endOf("month"),
    ]);
    const [loading, setLoading] = useState(false);
    const [newDate, setNewDate] = useState(null);
    const [selectData, setSelectData] = useState(null);
    const [display, setDisplay] = useState("block");

    const handleRangePickerChange = (dates) => {
        if (dates && dates.length === 2) {
            const formattedDates = dates.map((date) =>
                date.format("YYYY-MM-DD")
            );

            setDefaultDate(dates);
            setNewDate({ date: formattedDates });
        } else {
            const newStartDate = dayjs().startOf("month").format("YYYY-MM-DD");
            const newEndDate = dayjs().endOf("month").format("YYYY-MM-DD");

            setDefaultDate([dayjs(newStartDate), dayjs(newEndDate)]);
            setNewDate({ date: [newStartDate, newEndDate] });
        }
    };

    const handleSelectChange = (field, value) => {
        setSelectData({
            [field]: value != undefined ? value : null,
        });
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
                    <Col
                        style={{
                            display,
                        }}
                        xs={24}
                        sm={12}
                        md={8}
                        lg={6}
                    >
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
                }}
            >
                <div
                    style={{
                        paddingBlock: 10,
                        paddingInline: 24,
                        minHeight: 360,
                        background: colorBgContainer,
                        borderRadius: borderRadiusLG,
                    }}
                >
                    <Radar
                        loading={loading}
                        setLoading={setLoading}
                        date={newDate}
                        selectData={selectData}
                    />
                </div>
            </Content>
        </AuthenticatedLayout>
    );
}
